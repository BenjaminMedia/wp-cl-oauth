<?php

namespace Bonnier\WP\ClOauth\Http\Routes;

use Bonnier\WP\ClOauth\Helpers\Base64;
use Bonnier\WP\ClOauth\Helpers\RedirectHelper;
use Bonnier\WP\ClOauth\Http\Exceptions\HttpException;
use Bonnier\WP\ClOauth\Repository\CommonLoginRepository;
use Bonnier\WP\ClOauth\Services\AccessTokenService;
use Bonnier\WP\ClOauth\Services\CommonLoginOAuth;
use Bonnier\WP\ClOauth\Settings\SettingsPage;
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
     * @var
     */
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
        $redirectUri = $request->get_param('redirectUri');
        $state = $request->get_param('state');
        $postRequiredRole = null;

        // Persist auth destination
        $this->set_auth_destination($redirectUri);

        // Get user from admin service
        try {
            $repoClass = new CommonLoginRepository();
            $commonLoginUser = $repoClass->getUserFromLoginRequest($request);
            if (!$commonLoginUser) {
                $repoClass->triggerLoginFlow($state);
            }
        } catch (HttpException $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], $e->getCode());
        }

        if($state){
            $state = json_decode(Base64::UrlDecode($state));
            if(isset($state->purchase)) {
                if($accessToken = AccessTokenService::getAccessTokenFromStorage()){
                    RedirectHelper::redirect($this->getPaymentUrl($state->purchase, $state->product_url, $accessToken));
                }
            }
        }

        // Check if auth destination has been set
        $redirect = $this->get_auth_destination();

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

        $redirectUri = $request->get_param('redirectUri');

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
}
