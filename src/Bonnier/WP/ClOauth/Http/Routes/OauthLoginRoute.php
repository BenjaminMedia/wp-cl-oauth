<?php

namespace Bonnier\WP\ClOauth\Http\Routes;

use Bonnier\WP\ClOauth\Helpers\Base64;
use Bonnier\WP\ClOauth\Http\Client;
use Bonnier\WP\ClOauth\Http\Exceptions\HttpException;
use Bonnier\WP\ClOauth\Plugin;
use Bonnier\WP\ClOauth\Services\AccessTokenService;
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
    private $user;

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
            $commonLoginUser = $this->getCommonLoginUser($request);
        } catch (HttpException $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], $e->getCode());
        }

        if (!$commonLoginUser) {
            $this->trigger_login_flow($state, $redirectUri);
        }


        if($state){
            $state = json_decode(Base64::UrlDecode($state));
            if(isset($state->purchase)) {
                if($accessToken = AccessTokenService::getAccessTokenFromStorage()){
                    $this->redirect($this->getPaymentUrl($state->purchase, $state->product_url, $accessToken));
                }
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
     * Gets the cookie lifetime
     *
     * @return int
     */
    public static function get_user_cache_lifetime()
    {
        return time() + (OauthLoginRoute::USER_CACHE_LIFETIME_MINUTES * 60);
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

        $user = $this->getCommonLoginUser();
        if ($user) {
            return true;
        }

        /*$wpUser = new User();
        $wpUser->create_local_user($user, $this->get_oauth_service()->getCurrentAccessToken()); no local users for us :> */
        return false;
    }

    public function has_access($productId, $callbackUrl = false){
        if(!$callbackUrl){
            $callbackUrl = home_url('/');
        }
        if(!$this->is_authenticated()){
            return false;
        }
        $plugin = Plugin::instance();
        $client = new Client([
            'base_uri' => $plugin::PURCHASE_MANAGER_URL
        ]);
        if($accessToken = AccessTokenService::getAccessTokenFromStorage()){
            try{
                $response = $client->get('has_access',[
                    'body' => [
                        'access_token' => $accessToken,
                        'product_id' => $productId,
                        'callback' => $callbackUrl,
                    ],
                    'headers' => [
                        'Accept' => 'application/json'
                    ]
                ]);
            }
            catch(HttpException $e){
                //TODO: Fix this
                $this->redirect($this->getPaymentUrl($productId, $callbackUrl, $accessToken->getToken()));
            }

            if($response && 200 == $response->getStatusCode()){
                return true;
            }
        }

        return false;

    }

    public function getPaymentUrl($productId, $callbackUrl = false, $accessToken = false) {
        if(!$callbackUrl){
            $callbackUrl = home_url('/');
        }
        $plugin = Plugin::instance();
        if(!$accessToken){
            $accessToken = ($token = AccessTokenService::getAccessTokenFromStorage()) ? $token : false;
            if(!$this->is_authenticated()){
                return home_url('/').self::BASE_PREFIX.'/'.self::PLUGIN_PREFIX.'/'.self::VERSION.'/'.self::LOGIN_ROUTE.'?redirectUri='.
                $plugin::PURCHASE_MANAGER_URL.'has_access?access_token='.urlencode($accessToken).'&product_id='.urlencode($productId).'&callback='.urlencode($callbackUrl).'&state='.Base64::UrlEncode(json_encode(['purchase' => $productId]));
            }
        }
        return $plugin::PURCHASE_MANAGER_URL.'has_access?access_token='.urlencode($accessToken).'&product_id='.urlencode($productId).'&callback='.urlencode($callbackUrl);
    }
    /**
     * The function that handles the user logout request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function logout(WP_REST_Request $request) {

        AccessTokenService::destroyAccessTokenCookie();

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
            $this->get_oauth_service()->getAuthorizationUrl($options)
        );
    }

    private function getUserFromCacheOrSave($accessToken) {
        $accessTokenKey = Plugin::TEXT_DOMAIN.'-'.md5($accessToken);
        if($user = wp_cache_get($accessTokenKey) ){
            return json_decode($user);
        }
        if($user = $this->getUserByAccessToken($accessToken)){
            wp_cache_set($accessTokenKey, json_encode($user), Plugin::TEXT_DOMAIN ,
                self::get_user_cache_lifetime());
            return $user;
        }
        return false;
    }

    /**
     * Get the currently signed in user.
     *
     * @return mixed
     */
    private function getUserByAccessToken($accessToken = false)
    {
        if ($this->user !== null) {
            return $this->user;
        }
        if($accessTokenFromStorage = AccessTokenService::getAccessTokenFromStorage()){
            $AccessTokenInstance = AccessTokenService::ClassInstanceByToken($accessTokenFromStorage);
            if($AccessTokenInstance instanceof AccessToken){
                return $this->get_oauth_service()->getResourceOwner($AccessTokenInstance);
            }
            return $this->get_oauth_service()->getResourceOwner($AccessTokenInstance);
        }
        if(isset($accessToken)){
            $AccessTokenInstance = AccessTokenService::ClassInstanceByToken($accessToken);
            return $this->get_oauth_service()->getResourceOwner($AccessTokenInstance);
        }

        return false;
    }

    private function handleLoginRequest($request) {

        if ($request && $grantToken = $request->get_param('code')) {
            try {
                $accessToken = $this->get_oauth_service()->getAccessToken('authorization_code', [
                    'code' => $grantToken
                ]);
                return $this->getUserFromCacheOrSave(AccessTokenService::setAccessTokenToStorage($accessToken->getToken()));
            }
            catch(Exception $exception) {
                if(is_user_admin()){
                    echo var_dump($exception);
                }
            }
        }
        return false;
    }

    /**
     *
     * @return mixed
     */
    public function getCommonLoginUser($request = null)
    {
        if($accessToken = AccessTokenService::getAccessTokenFromStorage()) {
            return $this->getUserFromCacheOrSave($accessToken);
        }

        if(empty($request)) {
            return false;
        }

        if($user = $this->handleLoginRequest($request)) {
            return $user;
        }

        $this->trigger_login_flow($request->get_param('state'), $request->get_param('redirectUri'));
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
        if (!$this->service) {
            $locale = $this->settings->get_current_locale();

            $this->service = new CommonLoginOAuth([
                'clientId' => $this->settings->get_api_user($locale),
                'clientSecret' => $this->settings->get_api_secret($locale),
                'scopes' => [],
            ], $this->settings);
        }

        return $this->service;
    }

    public function get_oauth_state(){
        return $this->get_oauth_service()->getState();
    }
}
