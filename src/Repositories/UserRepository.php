<?php
namespace Bonnier\WP\OAuth\Repositories;

use Bonnier\WP\OAuth\Helpers\Base64;
use Bonnier\WP\OAuth\Http\Client;
use Bonnier\WP\OAuth\Providers\CommonLoginResourceOwner;
use Bonnier\WP\OAuth\Services\AccessTokenService;
use Bonnier\WP\OAuth\Services\CommonLoginOAuth;
use Bonnier\WP\OAuth\WpClOAuth;
use Bonnier\WP\OAuth\WpOAuth;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;

class UserRepository
{
    const USER_CACHE_LIFETIME_MINUTES = 60;

    /** @var CommonLoginResourceOwner */
    private $user;

    /**
     * Get the currently signed in user.
     *
     * @return mixed
     */
    public function getUser()
    {
        if($this->user) {
            return $this->user;
        }

        if($user = $this->getUserByAccessToken(AccessTokenService::getFromStorage())){
            $this->user = $user;
            return $user;
        }

        if($user = $this->getUserFromStorage()) {
            $this->user = $user;
            return $user;
        }

        return null;
    }

    public function setUserFromAccessToken(AccessToken $accessToken)
    {
        $this->user = WpOAuth::instance()->getOauthProvider()->getResourceOwner($accessToken);
        wp_cache_set(
            md5($accessToken->getToken()),
            json_encode($this->user->toArray()),
            WpOAuth::TEXT_DOMAIN,
            self::getUserCacheLifeTime()
        );
    }

    /**
     * Check if the current request is authenticated
     * @return bool
     */
    public function isAuthenticated()
    {
        return AccessTokenService::isValid();
    }

    public function getAccessToken()
    {
        return AccessTokenService::getFromStorage();
    }

    private function getUserFromStorage()
    {
        $accessToken = AccessTokenService::getFromStorage();
        $accessTokenKey = md5($accessToken->getToken());
        if($cachedUser = wp_cache_get($accessTokenKey, WpOAuth::TEXT_DOMAIN ) ){
            return new CommonLoginResourceOwner(json_decode($cachedUser));
        }
        if($user = self::getUserByAccessToken($accessToken))
        {
            wp_cache_set(
                $accessTokenKey,
                json_encode($user->toArray()),
                WpOAuth::TEXT_DOMAIN,
                self::getUserCacheLifeTime()
            );

            return $user;
        }
        return false;
    }

    /**
     * Gets the cache lifetime
     *
     * @return int seconds
     */
    private static function getUserCacheLifeTime()
    {
        return time() + (self::USER_CACHE_LIFETIME_MINUTES * 60);
    }
    
    /**
     * Get the currently signed in user.
     *
     * @param AccessToken $accessToken
     * @return ResourceOwnerInterface
     */
    public function getUserByAccessToken(AccessToken $accessToken)
    {
        return WpOAuth::instance()->getOauthProvider()->getResourceOwner($accessToken);
    }
}
