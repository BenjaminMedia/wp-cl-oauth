<?php

namespace Bonnier\WP\ClOauth\Http\Routes;

use Bonnier\WP\ClOauth\Helpers\Base64;
use Bonnier\WP\ClOauth\Http\Client;
use Bonnier\WP\ClOauth\Http\Exceptions\HttpException;
use Bonnier\WP\ClOauth\Plugin;
use Exception;
use Bonnier\WP\ClOauth\Services\CommonLoginOAuth;
use Bonnier\WP\ClOauth\Settings\SettingsPage;
use WP_REST_Request;
use WP_REST_Response;
use League\OAuth2\Client\Token\AccessToken;

class OauthLoginRoute
{
    const BASE_PREFIX = 'wp-json';

    /**
     * The namespace prefix.
     */
    const PLUGIN_PREFIX = 'bp-cl-oauth';

    /**
     * The namespace version.
     */
    const VERSION = 'v1';

    /**
     * The login route.
     */
    const LOGIN_ROUTE = '/oauth/login';

    /**
     * The logout route.
     */
    const LOGOUT_ROUTE = '/oauth/logout';

    /**
     * The access token cookie lifetime.
     */
    const ACCESS_TOKEN_LIFETIME_HOURS = 24;

    /**
     * The access token cookie lifetime.
     */
    const USER_CACHE_LIFETIME_MINUTES = 10;

    /**
     * The access token cookie key.
     */
    const ACCESS_TOKEN_COOKIE_KEY = 'bp_cl_oauth_token';

    /**
     * The auth destination cookie key.
     */
    const AUTH_DESTINATION_COOKIE_KEY = 'bp_cl_oauth_auth_destination';

    /* @var SettingsPage $settings */
    private $settings;

    /* @var CommonLoginOAuth $service */
    private $service;

    /**
     * OauthLoginRoute constructor.
     * @param SettingsPage $settings
     */
    public function __construct(SettingsPage $settings)
    {
        $this->settings = $settings;

        add_action('rest_api_init', function () {
            register_rest_route($this->get_route_namespace(), self::LOGIN_ROUTE, [
                'methods' => 'GET, POST',
                'callback' => [$this, 'login'],
            ]);
            register_rest_route($this->get_route_namespace(), self::LOGOUT_ROUTE, [
                'methods' => 'GET, POST',
                'callback' => [$this, 'logout'],
            ]);;
        });
    }

    /**
     * The function that handles the user login request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function login(WP_REST_Request $request)
    {
        $redirectUri = $request->get_param('redirectUri');
        $state = $request->get_param('state');
        $postRequiredRole = null;

        // Persist auth destination
        $this->set_auth_destination($redirectUri);

        // Get user from admin service
        try {
            $commonLoginUser = $this->get_common_login_user($request, $redirectUri);
        } catch (HttpException $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], $e->getCode());
        }

        // If the user is not logged in, we redirect to the login screen.
        if (!$commonLoginUser) {
            $this->trigger_login_flow($state, $redirectUri);
        }

        if($state){
            $state = json_decode(Base64::UrlDecode($state));
            if(isset($state->purchase)) {
                $this->redirect($this->getPaymentUrl($state->purchase, $state->product_url, $this->service->getCurrentAccessToken()->getToken()));
            }
        }

        // Check if auth destination has been set
        $redirect = $this->get_auth_destination();

        if (!$redirect) {
            // Redirect to home page
            $redirect = home_url('/');
        }

        $this->redirect($redirect);
    }

    /**
     * Check if the current request is authenticated
     *
     * @param null $postId
     *
     * @return bool
     */
    public function is_authenticated()
    {
        /*if(!$postId) {
            $postId = get_the_ID();
        }*/

        $this->service = $this->get_oauth_service();
        $user = $this->get_common_login_user();
        if ($user) {
            return true;
        }

        /*$wpUser = new User();
        $wpUser->create_local_user($user, $this->service->getCurrentAccessToken()); no local users for us :> */
        return false;
    }

    public function has_access($productId, $callbackUrl = false){
        if(!$callbackUrl){
            $callbackUrl = home_url('/');
        }
        if(!$this->is_authenticated()){
            return false;
        }
        $this->service = $this->get_oauth_service();
        $plugin = Plugin::instance();
        $client = new Client([
            'base_uri' => $plugin::PURCHASE_MANAGER_URL
        ]);
        $accessToken = $this->service->getCurrentAccessToken();
        try{
            $response = $client->get('has_access',[
                'body' => [
                    'access_token' => $accessToken->getToken(),
                    'product_id' => $productId,
                    'callback' => $callbackUrl,
                ],
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]);
        }
        catch(HttpException $e){
            $this->redirect($this->getPaymentUrl($productId, $callbackUrl, $accessToken->getToken()));
        }

        if($response && 200 == $response->getStatusCode()){
            return true;
        }

        return false;

    }

    public function getPaymentUrl($productId, $callbackUrl = false, $accessToken = false) {
        if(!$callbackUrl){
            $callbackUrl = home_url('/');
        }
        $this->service = $this->get_oauth_service();
        $plugin = Plugin::instance();
        if(!$accessToken){
            if(!$this->is_authenticated()){
                return home_url('/').self::BASE_PREFIX.'/'.self::PLUGIN_PREFIX.'/'.self::VERSION.'/'.self::LOGIN_ROUTE.'?redirectUri='.
                $plugin::PURCHASE_MANAGER_URL.'has_access?access_token='.urlencode($accessToken).'&product_id='.urlencode($productId).'&callback='.urlencode($callbackUrl).'&state='.Base64::UrlEncode(json_encode(['purchase' => $productId]));
            }
            $accessToken = $this->service->getCurrentAccessToken()->getToken();
        }
        $user = $this->get_common_login_user();
        return $plugin::PURCHASE_MANAGER_URL.'has_access?access_token='.urlencode($accessToken).'&product_id='.urlencode($productId).'&callback='.urlencode($callbackUrl);
    }

    /**
     * The function that handles the user logout request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function logout(WP_REST_Request $request) {

        $this->destroy_access_token();

        if($this->settings->get_create_local_user($this->settings->get_current_locale())) {
            wp_logout();
        }

        $redirectUri = $request->get_param('redirectUri');

        if($redirectUri) {
            $this->redirect($redirectUri);
        }

        return new WP_REST_Response('ok', 200);
    }


    /**
     * Triggers the login flow by redirecting the user to the login Url
     * @param null $requiredRole
     */
    private function trigger_login_flow($state)
    {
        $options = [
            'state' => $state
        ];
        $this->redirect(
            $this->service->getAuthorizationUrl($options)
        );
    }

    /**
     *
     *
     * @param WP_REST_Request|null $request
     * @return mixed
     * @throws Exception|HttpException
     */
    public function get_common_login_user($request = null, $callback = null)
    {
        $this->service = $this->get_oauth_service();
        $cacheTimeout = $this->get_user_cache_lifetime();
        if($accessToken = $this->service->getCurrentAccessToken()){
            $accessTokenKey = Plugin::TEXT_DOMAIN.'-'.md5($accessToken->getToken());
            if($user = wp_cache_get( $accessTokenKey) ){
                return json_decode($user);
            }
            wp_cache_set($accessTokenKey, json_encode($this->service->getUser()), Plugin::TEXT_DOMAIN , $cacheTimeout);
            return $this->service->getUser();
        }
        try {
            if ($request && $grantToken = $request->get_param('code')) {
                $accessToken = $this->service->getAccessToken('authorization_code', [
                    'code' => $grantToken
                ]);

                $this->service->setAccessToken($accessToken);
                $this->set_access_token_cookie($accessToken);
                $accessTokenKey = Plugin::TEXT_DOMAIN.'-'.md5($this->service->getCurrentAccessToken());
                wp_cache_set($accessTokenKey, json_encode($this->service->getUser()), Plugin::TEXT_DOMAIN , $cacheTimeout);
                return $this->service->getUser();
            }

            elseif ($accessToken = $this->get_access_token()) {
                $this->service->setAccessToken($accessToken);
                $this->set_access_token_cookie($accessToken);
                $accessTokenKey = Plugin::TEXT_DOMAIN.'-'.md5($this->service->getCurrentAccessToken());
                wp_cache_set($accessTokenKey, json_encode($this->service->getUser()), Plugin::TEXT_DOMAIN , $cacheTimeout);
                return $this->service->getUser();
            }
        }
        catch(Exception $exception) {
            if(is_user_admin()){
                echo var_dump($exception);
            }
        }
        return false;

    }

    /**
     * Redirect the user to provided path
     *
     * @param $to
     */
    private function redirect($to)
    {
        header("Location: " . $to);
        exit();
    }

    /**
     * Returns the route namespace
     *
     * @return string
     */
    private function get_route_namespace()
    {
        return self::PLUGIN_PREFIX . '/' . self::VERSION;
    }

    /**
     * Returns the persisted access token or false
     *
     * @return AccessToken|bool
     */
    private function get_access_token()
    {
        return isset($_COOKIE[self::ACCESS_TOKEN_COOKIE_KEY]) ? $_COOKIE[self::ACCESS_TOKEN_COOKIE_KEY] : false;
    }

    /**
     * Persists the Access token for later use
     *
     * @param $token
     */
    private function set_access_token_cookie($token)
    {
        setcookie(self::ACCESS_TOKEN_COOKIE_KEY, $token, $this->get_access_token_lifetime(), '/');
    }

    private function destroy_access_token() {
        if(isset($_COOKIE[self::ACCESS_TOKEN_COOKIE_KEY])) {
            unset($_COOKIE[self::ACCESS_TOKEN_COOKIE_KEY]);
        }
        setcookie(self::ACCESS_TOKEN_COOKIE_KEY, '', time() - 3600, '/');
    }

    /**
     * Gets the cookie lifetime
     *
     * @return int
     */
    private function get_access_token_lifetime()
    {
        return time() + (self::ACCESS_TOKEN_LIFETIME_HOURS * 60 * 60);
    }

    /**
     * Gets the cookie lifetime
     *
     * @return int
     */
    private function get_user_cache_lifetime()
    {
        return time() + (self::USER_CACHE_LIFETIME_MINUTES * 60);
    }

    /**
     * Persist the auth destination in a cookie
     *
     * @param $destination
     */
    private function set_auth_destination($destination)
    {
        setcookie(self::AUTH_DESTINATION_COOKIE_KEY, $destination, time() + (1 * 60 * 60), '/');
    }

    /**
     * Get the auth destination from the cookie
     *
     * @return bool
     */
    private function get_auth_destination()
    {
        return isset($_COOKIE[self::AUTH_DESTINATION_COOKIE_KEY]) ? $_COOKIE[self::AUTH_DESTINATION_COOKIE_KEY] : false;
    }

    /**
     * Returns an instance of ServiceOauth
     *
     * @return CommonLoginOAuth
     */
    private function get_oauth_service()
    {
        if ($this->service) {
            return $this->service;
        }

        $locale = $this->settings->get_current_locale();

        return new CommonLoginOAuth([
            'clientId' => $this->settings->get_api_user($locale),
            'clientSecret' => $this->settings->get_api_secret($locale),
            'scopes' => [],
        ], $this->settings);
    }

    public function get_oauth_state(){
        return $this->get_oauth_service()->getState();
    }
}
