<?php

namespace Bonnier\WP\OAuth\Http;

use Bonnier\WP\OAuth\Helpers\NoCacheHeader;
use Bonnier\WP\OAuth\Helpers\RedirectHelper;
use Bonnier\WP\OAuth\Services\AccessTokenService;
use Bonnier\WP\OAuth\WpOAuth;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Routes
 * @package Bonnier\WP\ClOauth\Http
 */
class Routes
{
    const BASE_PREFIX = 'wp-json';

    const PLUGIN_PREFIX = 'bp-oauth';

    const LOGIN_ROUTE = '/oauth/login';

    const CALLBACK_ROUTE = '/oauth/callback';

    const LOGOUT_ROUTE = '/oauth/logout';

    private $homeUrl;

    public function __construct()
    {

        if(function_exists('pll_home_url')) {
            $this->homeUrl = pll_home_url();
        } else {
            $this->homeUrl = home_url('/');
        }

        add_action('rest_api_init', function () {
            register_rest_route(self::PLUGIN_PREFIX, self::LOGIN_ROUTE, [
                'methods' => 'GET, POST',
                'callback' => [$this, 'login'],
            ]);
            register_rest_route(self::PLUGIN_PREFIX, self::CALLBACK_ROUTE, [
                'methods' => 'GET, POST',
                'callback' => [$this, 'callback'],
            ]);
            register_rest_route(self::PLUGIN_PREFIX, self::LOGOUT_ROUTE, [
                'methods' => 'GET, POST',
                'callback' => [$this, 'logout'],
            ]);
        });
    }

    /**
     * The function that handles the user login request
     *
     * @param WP_REST_Request $request
     */
    public function login(WP_REST_Request $request)
    {
        $redirect_uri = $request->get_param('redirect_uri') ?: $this->homeUrl;

        if(AccessTokenService::isValid()) {
            RedirectHelper::redirect($redirect_uri);
        }

        $authUrl = WpOAuth::instance()->getOauthProvider()->getAuthorizationUrl();

        $_SESSION['oauth2state'] = WpOAuth::instance()->getOauthProvider()->getState();
        $_SESSION['oauth2redirect'] = $redirect_uri;

        RedirectHelper::redirect($authUrl);
    }

    /**
     * The function that handles the OAuth service callback request
     *
     * @param WP_REST_Request $request
     */
    public function callback(WP_REST_Request $request)
    {
        if(!$this->validateState($request->get_param('state') ?? null)) {
            // Request has been tinkered with - let's forget about it and return home.
            RedirectHelper::redirect($this->homeUrl);
        }

        $accessToken = WpOAuth::instance()->getOauthProvider()->getAccessToken('authorization_code', [
            'code' => $request->get_param('code') ?? null,
        ]);

        WpOAuth::instance()->getUserRepo()->setUserFromAccessToken($accessToken);

        AccessTokenService::setToStorage($accessToken);

        RedirectHelper::redirect($_SESSION['oauth2redirect'] ?? $this->homeUrl);
    }



    /**
     * The function that handles the user logout request
     *
     * @param WP_REST_Request $request
     */
    public function logout(WP_REST_Request $request)
    {
        AccessTokenService::destroyCookies();

        $redirect_uri = $request->get_param('redirect_uri') ?? $this->homeUrl;

        $logoutUrl = WpOAuth::instance()->getOauthProvider()->getLogoutUrl($redirect_uri);

        RedirectHelper::redirect($logoutUrl);
    }

    public function getLoginRoute()
    {
        return sprintf(
            '/%s/%s/%s',
            static::BASE_PREFIX,
            static::PLUGIN_PREFIX,
            trim(static::LOGIN_ROUTE, '/')
            );
    }

    public function getLogoutRoute()
    {
        return sprintf(
            '/%s/%s/%s',
            static::BASE_PREFIX,
            static::PLUGIN_PREFIX,
            trim(static::LOGOUT_ROUTE, '/')
        );
    }

    /**
     * Validate, that state hasn't been changed
     *
     * @param string $state
     * @return bool
     */
    private function validateState($state)
    {
        return isset($_SESSION['oauth2state']) &&
            hash_equals($_SESSION['oauth2state'], $state);
    }
}
