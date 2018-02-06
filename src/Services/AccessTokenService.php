<?php

namespace Bonnier\WP\OAuth\Services;

use Bonnier\WP\OAuth\Providers\CommonLoginResourceOwner;
use Bonnier\WP\OAuth\WpOAuth;
use League\OAuth2\Client\Token\AccessToken;
use RuntimeException;

class AccessTokenService
{
    const ACCESS_TOKEN_EXPIRES_DAYS = 15;

    const ACCESS_TOKEN_COOKIE_KEY = 'bp_oauth_token';

    const EXPIRATION_COOKIE_KEY = 'bp_oauth_expires';

    const NO_CACHE_COOKIE = 'wordpress_logged_in_nocache';

    const USERNAME_COOKIE = 'bp_oauth_username';

    public static function destroyCookies()
    {
        self::deleteCookie(self::ACCESS_TOKEN_COOKIE_KEY);
        self::deleteCookie(self::EXPIRATION_COOKIE_KEY);
        self::deleteCookie(self::NO_CACHE_COOKIE);
        self::deleteCookie(self::USERNAME_COOKIE);
    }

    /**
     * @return AccessToken|null
     */
    public static function getFromStorage()
    {
        if($accessToken = self::getTokenFromCookie()) {
            return self::refreshToken($accessToken);
        }
        return null;
    }

    public static function isValid()
    {
        return !is_null(self::getFromStorage());
    }


    public static function setToStorage(AccessToken $accessToken)
    {
        self::refreshToken($accessToken);
        self::setCookie($accessToken);
        return $accessToken;
    }

    /**
     * Returns the current Access Token from a cookie or false
     *
     * @return AccessToken|bool
     */
    private static function getTokenFromCookie()
    {
        if (isset($_COOKIE[self::ACCESS_TOKEN_COOKIE_KEY])) {
            return self::convertToInstance($_COOKIE[self::ACCESS_TOKEN_COOKIE_KEY] ?? null, $_COOKIE[self::EXPIRATION_COOKIE_KEY] ?? null);
        }

        return null;
    }

    /**
     * Stores the current Access Token in a cookie
     *
     * @param AccessToken $accessToken
     */
    private static function setCookie(AccessToken $accessToken)
    {
        setcookie(
            self::ACCESS_TOKEN_COOKIE_KEY,
            $accessToken->getToken(),
            self::cookieLifetime(),
            '/'
        );
        setcookie(
            self::EXPIRATION_COOKIE_KEY,
            self::cookieLifetime(),
            self::cookieLifetime(),
            '/'
        );
        setcookie(
            self::NO_CACHE_COOKIE,
            '1',
            self::cookieLifetime(),
            '/'
        );

        /** @var CommonLoginResourceOwner $user */
        $user = WpOAuth::instance()->getUserRepo()->getUser();
        if($user) {
            setcookie(self::USERNAME_COOKIE, $user->getFirstName(), self::cookieLifetime(), '/');
        }
    }

    /**
     * Gets the cookie lifetime
     *
     * @return int
     */
    private static function cookieLifetime()
    {
        return time() + (self::ACCESS_TOKEN_EXPIRES_DAYS * 24 * 60 * 60);
    }

    private static function deleteCookie($key)
    {
        if(isset($_COOKIE[$key])) {
            unset($_COOKIE[$key]);
        }

        setcookie($key, '', time() - 3600, '/');
    }

    /**
     * Convert access token string to AccessToken instance
     *
     * @param string $accessToken
     *
     * @return AccessToken|null
     */
    private static function convertToInstance($accessToken, $expires = null)
    {
        if(!$accessToken) {
            return null;
        }

        if($accessToken instanceof AccessToken) {
            return $accessToken;
        }

        $options = [
            'access_token' => $accessToken,
        ];
        if($expires) {
            $options['expires_in'] = $expires;
        }

        return new AccessToken($options);
    }

    private static function refreshToken(AccessToken $accessToken)
    {
        if(!static::hasExpired($accessToken)) {
            return $accessToken;
        }
        $refreshedAccessToken = WpOAuth::instance()->getOauthProvider()->getAccessToken('refresh_token', [
            'refresh_token' => $accessToken->getRefreshToken()
        ]);

        if($refreshedAccessToken && $refreshedAccessToken->getToken()) {

            self::setCookie($refreshedAccessToken);

            return $refreshedAccessToken;
        } else {
            self::destroyCookies();

            return null;
        }
    }
    
    /**
     * @param AccessToken $accessToken
     * @return bool
     */
    private static function hasExpired(AccessToken $accessToken)
    {
        try {
            return $accessToken->hasExpired();
        } catch(RuntimeException $e) {
            $expires = $_COOKIE[self::EXPIRATION_COOKIE_KEY] ?? null;
            return is_null($expires) || $expires < time();
        }
    }
}