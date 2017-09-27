<?php

namespace Bonnier\WP\ClOauth\Services;


use Bonnier\WP\ClOauth\Http\Routes\OauthLoginRoute;
use Bonnier\WP\ClOauth\Repository\CommonLoginRepository;
use Bonnier\WP\ClOauth\Settings\SettingsPage;
use League\OAuth2\Client\Token\AccessToken;

class AccessTokenService
{
    private $settings;
    private $oauthService;
    /**
     * @var object Instance of this class.
     */
    private static $instance;
    /**
     * The access token cookie lifetime.
     */
    const ACCESS_TOKEN_LIFETIME_HOURS = 24;
    /**
     * The access token cookie key.
     */
    const ACCESS_TOKEN_COOKIE_KEY = 'bp_cl_oauth_token';

    const NO_CACHE_COOKIE = 'wordpress_logged_in_nocache';

    const USERNAME_COOKIE = 'bp_cl_oauth_username';

    /**
     * The auth destination cookie key.
     */
    const AUTH_DESTINATION_COOKIE_KEY = 'bp_cl_oauth_auth_destination';

    /**
     * Returns the current Access Token from a cookie or false
     *
     * @return AccessToken|bool
     */
    public static function getTokenFromCookie()
    {
        return isset($_COOKIE[self::ACCESS_TOKEN_COOKIE_KEY]) ? $_COOKIE[self::ACCESS_TOKEN_COOKIE_KEY] : false;
    }

    /**
     * Stores the current Access Token in a cookie
     *
     * @param $token
     */
    private static function setAccessTokenCookie($token)
    {
        setcookie(self::ACCESS_TOKEN_COOKIE_KEY, $token, self::accessTokenCookieLifetime(),
            '/');
        setcookie(self::NO_CACHE_COOKIE, '1', self::accessTokenCookieLifetime(),
            '/');
        $clRepo = new CommonLoginRepository();
        $user = $clRepo->getUser($token);
        if($user) {
            setcookie(self::USERNAME_COOKIE, $user->first_name, self::accessTokenCookieLifetime(), '/');
        }
    }

    /**
     * Gets the cookie lifetime
     *
     * @return int
     */
    private static function accessTokenCookieLifetime()
    {
        return time() + (self::ACCESS_TOKEN_LIFETIME_HOURS * 60 * 60);
    }

    public static function destroyAccessTokenCookie()
    {
        if (isset($_COOKIE[self::ACCESS_TOKEN_COOKIE_KEY])) {
            unset($_COOKIE[self::ACCESS_TOKEN_COOKIE_KEY]);
        }
        setcookie(self::ACCESS_TOKEN_COOKIE_KEY, '', time() - 3600, '/');
        setcookie(self::NO_CACHE_COOKIE, '', time() - 3600, '/');
        setcookie(self::USERNAME_COOKIE, '', time() - 3600, '/');

    }

    public static function ClassInstanceByToken($accessToken)
    {
        if(isset($accessToken) && !empty($accessToken)){
            $options = ['access_token' => $accessToken];
            return new AccessToken($options);
        }
        return null;
    }

    /**
     * Returns an instance of ServiceOauth
     *
     * @return CommonLoginOAuth
     */
    public function getOAuthService()
    {
        if (!$this->oauthService) {
            $this->settings = new SettingsPage();
            $locale = $this->settings->get_current_locale();

            $this->oauthService = new CommonLoginOAuth([
                'clientId' => $this->settings->get_api_user($locale),
                'clientSecret' => $this->settings->get_api_secret($locale),
                'scopes' => [],
            ], $this->settings);
        }

        return $this->oauthService;
    }

    /**
     * Returns the instance of this class.
     */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }


    /**
     * @return AccessToken|null
     */
    public static function getAccessTokenFromStorage()
    {
        if($accessToken = self::ClassInstanceByToken(self::getTokenFromCookie())) {
            return $accessToken;
        }
        return self::ClassInstanceByToken(self::instance()->getOAuthService()->getCurrentAccessToken());
    }


    public static function setAccessTokenToStorage($accessToken)
    {
        self::instance()->getOAuthService()->setAccessToken($accessToken);
        self::setAccessTokenCookie($accessToken);
        return $accessToken;
    }
}