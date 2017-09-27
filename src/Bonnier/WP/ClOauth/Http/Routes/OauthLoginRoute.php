<?php

namespace Bonnier\WP\ClOauth\Http\Routes;

use Bonnier\WP\ClOauth\Helpers\Base64;
use Bonnier\WP\ClOauth\Helpers\RedirectHelper;
use Bonnier\WP\ClOauth\Http\Exceptions\HttpException;
use Bonnier\WP\ClOauth\Repository\CommonLoginRepository;
use Bonnier\WP\ClOauth\Services\AccessTokenService;
use Bonnier\WP\ClOauth\Services\CommonLoginOAuth;
use Bonnier\WP\ClOauth\Settings\SettingsPage;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class OauthLoginRoute
 * @package Bonnier\WP\ClOauth\Http\Routes
 */
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

    /* @var SettingsPage $settings */
    private $settings;

    /**
     * OauthLoginRoute constructor.
     * @param SettingsPage $settings
     */
    public function __construct(SettingsPage $settings)
    {
        $this->settings = $settings;

        $accessToken = AccessTokenService::getTokenFromCookie();
        if($accessToken) {
            header('Cache-Control: no-cache');
        }

        add_action('rest_api_init', function () {
            register_rest_route($this->get_route_namespace(), self::LOGIN_ROUTE, [
                'methods' => 'GET, POST',
                'callback' => [$this, 'login'],
            ]);
            register_rest_route($this->get_route_namespace(), self::LOGOUT_ROUTE, [
                'methods' => 'GET, POST',
                'callback' => [$this, 'logout'],
            ]);
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

        $redirectUri = $request->get_param('redirectUri'); // Where should we return on successful login
        $state = $request->get_param('state'); // Login: 'undefined'
        $accessToken = $request->get_param('accessToken'); // Login: null
        $postRequiredRole = null;

        $repoClass = new CommonLoginRepository();

        // Persist auth destination
        // Check if auth destination has been set
        if(!$redirect = $repoClass->getAuthDestination()){
           $redirect = $repoClass->setAuthDestination($redirectUri);
        }

        if(isset($accessToken) && !empty($accessToken)){
            AccessTokenService::setAccessTokenToStorage($accessToken);
            RedirectHelper::redirect($redirectUri);
        }

        // Get user from admin service
        try {
            $commonLoginUser = $repoClass->getUserFromLoginRequest($request);
            if (!$commonLoginUser) {
                $repoClass->triggerLoginFlow($state);
            }
        } catch (IdentityProviderException $exception) {
            $repoClass->triggerLoginFlow($state);
            //return new WP_REST_Response(['error' => $e->getMessage()], $e->getCode());
        }

        //TODO: kill this, so that login can work without purchase
        if($state){
            $state = json_decode(Base64::UrlDecode($state));
            if(isset($state->purchase)) {
                if($accessToken = AccessTokenService::getAccessTokenFromStorage()){
                    RedirectHelper::redirect($repoClass->getPaymentUrl($state->purchase, $state->product_url, $accessToken, $state->product_preview));
                }
            }
        }

        $redirect = $repoClass->getAuthDestination();

        if (!$redirect) {
            // Redirect to home page
            $redirect = home_url('/');
        }

        RedirectHelper::redirect($redirect);
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

        $redirectUri = $this->settings->get_api_endpoint().'logout?redirect_to='.urlencode($request->get_param('redirectUri'));

        if($redirectUri) {
            RedirectHelper::redirect($redirectUri);
        }

        return new WP_REST_Response('ok', 200);
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
}
