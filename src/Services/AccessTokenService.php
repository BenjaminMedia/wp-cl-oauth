<?php

namespace Bonnier\WP\OAuth\Services;

use Bonnier\WP\OAuth\Helpers\RedirectHelper;
use Bonnier\WP\OAuth\Providers\CommonLoginResourceOwner;
use Bonnier\WP\OAuth\WpOAuth;
use League\OAuth2\Client\Token\AccessToken;

class AccessTokenService
{
    const ACCESS_TOKEN_EXPIRES_DAYS = 15;
    
    const ACCESS_TOKEN_COOKIE_KEY = 'bp_oauth_token';
    
    const NO_CACHE_COOKIE = 'wordpress_logged_in_nocache';
    
    const USERNAME_COOKIE = 'bp_oauth_username';
    
    const DATALAYER_TRACKING_ID = 'bp_oauth_tracking_id';
	
    public static function destroyCookies()
    {
        self::deleteCookie(self::ACCESS_TOKEN_COOKIE_KEY);
        self::deleteCookie(self::NO_CACHE_COOKIE);
        self::deleteCookie(self::USERNAME_COOKIE);
        self::deleteCookie(self::DATALAYER_TRACKING_ID);
    }
    
    /**
     * @return AccessToken
     */
    public static function getFromStorage()
    {
        if ($accessToken = self::getTokenFromCookie()) {
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
            return self::convertToInstance($_COOKIE[self::ACCESS_TOKEN_COOKIE_KEY] ?? null);
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
            json_encode($accessToken->jsonSerialize()),
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
        if ($user) {
        	setcookie(self::USERNAME_COOKIE, $user->getFirstName(), self::cookieLifetime(), '/');
			setcookie(self::DATALAYER_TRACKING_ID, hash('sha256',$user->getEmail()), self::cookieLifetime(), '/');
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
        if (isset($_COOKIE[$key])) {
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
    private static function convertToInstance($accessToken)
    {
        if (!$accessToken) {
            return null;
        }
        
        if ($accessToken instanceof AccessToken) {
            return $accessToken;
        }
    
        $accessToken = stripslashes($accessToken);
    
        $token = json_decode($accessToken, $associativeArray = true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ?
                "https://" : "http://";
            RedirectHelper::redirect(
                WpOAuth::instance()->getRoutes()->getLogoutRoute() .
                '?redirect_uri=' . urlencode($protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])
            );
        }
    
        return new AccessToken($token);
    }
    
    private static function refreshToken(AccessToken $accessToken)
    {
        if (!$accessToken->hasExpired()) {
            return $accessToken;
        }
        
        if ($refreshToken = $accessToken->getRefreshToken()) {
            $refreshedAccessToken = WpOAuth::instance()->getOauthProvider()->getAccessToken('refresh_token', [
                'refresh_token' => $refreshToken
            ]);
            
            if ($refreshedAccessToken && $refreshedAccessToken->getToken()) {
                self::setCookie($refreshedAccessToken);
                
                return $refreshedAccessToken;
            }
        }
        self::destroyCookies();
        
        return null;
    }
}
