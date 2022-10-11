<?php

namespace Bonnier\WP\OAuth\Http;

use Bonnier\WP\OAuth\Http\Responses\NoCacheRedirectRestResponse;
use Bonnier\WP\OAuth\Services\AccessTokenService;
use Bonnier\WP\OAuth\WpOAuth;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Routes
 * @package Bonnier\WP\Oauth\Http
 */
class Routes
{
    const BASE_PREFIX = 'wp-json';

    const PLUGIN_PREFIX = 'bp-oauth';

    const LOGIN_ROUTE = '/oauth/login';

    const REGISTER_ROUTE = '/oauth/register';

    const CALLBACK_ROUTE = '/oauth/callback';

    const LOGOUT_ROUTE = '/oauth/logout';

    const SUBSCRIPTION_ROUTE = '/subscription_number';

    private $homeUrl;

    public function __construct()
    {

        if (function_exists('pll_home_url')) {
            $this->homeUrl = pll_home_url();
        } else {
            $this->homeUrl = home_url('/');
        }

        add_action('rest_api_init', function () {
            register_rest_route(self::PLUGIN_PREFIX, self::REGISTER_ROUTE, [
                'methods' => 'GET, POST',
                'callback' => [$this, 'register'],
            ]);
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
            register_rest_route(self::PLUGIN_PREFIX, self::SUBSCRIPTION_ROUTE, [
                'methods' => 'POST',
                'callback' => [$this, 'subscription'],
            ]);
        });
    }

    /**
     * The function that handles the user login request
     *
     * @param WP_REST_Request $request
     * @return NoCacheRedirectRestResponse
     */
    public function register(WP_REST_Request $request)
    {
        $response = $this->login($request);
        $response->headers['Location'] .= '&create=true';
        return $response;
    }


    /**
     * The function that handles the user login request
     *
     * @param WP_REST_Request $request
     * @return NoCacheRedirectRestResponse
     */
    public function login(WP_REST_Request $request)
    {
        $redirect_uri = urldecode($request->get_param('redirect_uri') ?: $this->homeUrl);

        AccessTokenService::destroyOtherBigCookies();

        if (AccessTokenService::isValid()) {
            return new NoCacheRedirectRestResponse($redirect_uri);
        }

        $authUrl = WpOAuth::instance()->getOauthProvider()->getAuthorizationUrl();

        $_SESSION['oauth2state'] = WpOAuth::instance()->getOauthProvider()->getState();
        $_SESSION['oauth2redirect'] = $redirect_uri;

        return new NoCacheRedirectRestResponse($authUrl);
    }

    /**
     * The function that handles the OAuth service callback request
     *
     * @param WP_REST_Request $request
     * @return NoCacheRedirectRestResponse
     */
    public function callback(WP_REST_Request $request)
    {
        AccessTokenService::destroyOtherBigCookies();
        
        if (!$this->validateState($request->get_param('state') ?? null)) {
            // Request has been tinkered with - let's forget about it and return home.
            return new NoCacheRedirectRestResponse($this->homeUrl);
        }

        $accessToken = WpOAuth::instance()->getOauthProvider()->getAccessToken('authorization_code', [
            'code' => $request->get_param('code') ?? null,
        ]);

        $redirect = $_SESSION['oauth2redirect'] ?? $this->homeUrl;

        if (WpOAuth::instance()->getUserRepo()->setUserFromAccessToken($accessToken)) {
            AccessTokenService::setToStorage($accessToken);
            // Add 'cl-login=success' in url for datalayer.js to fetch common-login login successful action
            return new NoCacheRedirectRestResponse($redirect.'?cl-login=success');
        }
	    return $this->triggerLoginFailure($redirect);
    }

    /**
     * The function that handles the user logout request
     *
     * @param WP_REST_Request $request
     * @return NoCacheRedirectRestResponse
     */
    public function logout(WP_REST_Request $request)
    {
        AccessTokenService::destroyCookies();

        $redirect_uri = $request->get_param('redirect_uri') ?? $this->homeUrl;

        $logoutUrl = WpOAuth::instance()->getOauthProvider()->getLogoutUrl($redirect_uri);

        return new NoCacheRedirectRestResponse($logoutUrl);
    }

    public function subscription(WP_REST_Request $request)
    {
        $subNo = $request->get_param('no');
        if ($subNo) {
            $response = WpOAuth::instance()->getOauthProvider()->updateSubscriptionNumber($subNo);
            return new WP_REST_Response($response, $response->status);
        }
        return new WP_REST_Response('invalid input', 400);
    }

    public function getRoute($route)
    {
        return sprintf(
            '/%s/%s/%s',
            static::BASE_PREFIX,
            static::PLUGIN_PREFIX,
            trim($route, '/')
        );
    }

    public function getURI($route)
    {
        return sprintf(
            '%s/%s',
            trim($this->homeUrl, '/'),
            trim($this->getRoute($route), '/')
        );
    }

    public function getRegisterRoute()
    {
        return $this->getRoute(static::REGISTER_ROUTE);
    }

    public function getLoginRoute()
    {
        return $this->getRoute(static::LOGIN_ROUTE);
    }

    public function getCallbackRoute()
    {
        return $this->getRoute(static::CALLBACK_ROUTE);
    }

    public function getLogoutRoute()
    {
        return $this->getRoute(static::LOGOUT_ROUTE);
    }

    public function getSubscriptionRoute()
    {
        return $this->getRoute(static::SUBSCRIPTION_ROUTE);
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

    private function triggerLoginFailure($redirect)
    {
        $message = 'An error occured during login - please try again.';
        if (function_exists('pll__') && function_exists('pll_register_string')) {
            pll_register_string($message, $message);
            $message = pll__($message);
        }
        setcookie('bp_oauth_fail', $message, time() + 120, '/'); //Expires in two minutes
        return new NoCacheRedirectRestResponse($this->getLogoutRoute() . '?redirect_uri=' . urlencode($redirect));
    }
}
