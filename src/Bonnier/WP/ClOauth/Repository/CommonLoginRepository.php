<?php
namespace Bonnier\WP\ClOauth\Repository;

use Bonnier\WP\ClOauth\Helpers\RedirectHelper;
use Bonnier\WP\ClOauth\Http\Client;
use Bonnier\WP\ClOauth\Http\Exceptions\HttpException;
use Bonnier\WP\ClOauth\Http\Routes\OauthLoginRoute;
use Bonnier\WP\ClOauth\Plugin;
use Bonnier\WP\ClOauth\Services\AccessTokenService;
use Bonnier\WP\ClOauth\Services\CommonLoginOAuth;
use League\OAuth2\Client\Token\AccessToken;

class CommonLoginRepository
{
    protected $oAuthService;
    /**
     * The access token cookie lifetime.
     */
    const USER_CACHE_LIFETIME_MINUTES = 10;

    /**
     * @param $accessToken
     * @return array|bool|mixed|object
     */
    public function getUserFromCacheOrSave($accessToken) {
        $accessTokenKey = Plugin::TEXT_DOMAIN.'-'.md5($accessToken);
        if($user = wp_cache_get($accessTokenKey) ){
            return json_decode($user);
        }
        if($user = self::getUserByAccessToken($accessToken)){
            wp_cache_set($accessTokenKey, json_encode($user), Plugin::TEXT_DOMAIN ,
                self::getUserCacheLifeTime());
            return $user;
        }
        return false;
    }

    /**
     * @param $request
     * @return array|bool|mixed|object
     */
    public function getUserFromLoginRequest($request = null)
    {

        if ($request && $grantToken = $request->get_param('code')) {
            try {
                $accessToken = $this->getOAuthService()->getAccessToken('authorization_code', [
                    'code' => $grantToken
                ]);
                return CommonLoginRepository::getUserFromCacheOrSave(AccessTokenService::setAccessTokenToStorage($accessToken->getToken()));
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
     * Get the currently signed in user.
     *
     * @return mixed
     * @throws Exception
     */
    public function getUser($accessToken = false)
    {
        if(!isset($accessToken)) {
            $accessToken = AccessTokenService::getAccessTokenFromStorage();
        }

        if($user = $this->getUserFromCacheOrSave($accessToken)){
            return $user;
        }

        if($user = $this->getOAuthService()->getUser($accessToken)){
            return $user;
        }

        return false;
    }

    /**
     * Check if the current request is authenticated
     * @return bool
     */
    public function isAuthenticated()
    {
        /*if(!$postId) {
            $postId = get_the_ID();
        }*/
        $repoClass = new CommonLoginRepository();
        if ($repoClass->getUser()) {
            return true;
        }

        /*$wpUser = new User();
        $wpUser->create_local_user($user, $this->get_oauth_service()->getCurrentAccessToken()); no local users for us :> */
        return false;
    }

    /**
     * @param $productId
     * @param bool|false $callbackUrl
     * @return bool
     */
    public function hasAccessTo($productId, $callbackUrl = false){
        if(!$callbackUrl){
            $callbackUrl = home_url('/');
        }
        if(!$this->isAuthenticated()){
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
                RedirectHelper::redirect($this->getPaymentUrl($productId, $callbackUrl, $accessToken->getToken()));
            }

            if($response && 200 == $response->getStatusCode()){
                return true;
            }
        }

        return false;

    }

    /**
     * @param $productId
     * @param bool|false $callbackUrl
     * @param bool|false $accessToken
     * @return string
     */
    public function getPaymentUrl($productId, $callbackUrl = false, $accessToken = false) {
        if(!$callbackUrl){
            $callbackUrl = home_url('/');
        }
        $plugin = Plugin::instance();
        if(!$accessToken){
            $accessToken = ($token = AccessTokenService::getAccessTokenFromStorage()) ? $token : false;
            if(!$this->isAuthenticated()){
                return home_url('/').OauthLoginRoute::BASE_PREFIX.'/'.OauthLoginRoute::PLUGIN_PREFIX.'/'.OauthLoginRoute::VERSION.'/'.OauthLoginRoute::LOGIN_ROUTE.'?redirectUri='.
                $plugin::PURCHASE_MANAGER_URL.'has_access?access_token='.urlencode($accessToken).'&product_id='.urlencode($productId).'&callback='.urlencode($callbackUrl).'&state='.Base64::UrlEncode(json_encode(['purchase' => $productId]));
            }
        }
        return $plugin::PURCHASE_MANAGER_URL.'has_access?access_token='.urlencode($accessToken).'&product_id='.urlencode($productId).'&callback='.urlencode($callbackUrl);
    }

    /**
     * Gets the cookie lifetime
     *
     * @return int
     */
    private static function getUserCacheLifeTime()
    {
        return time() + (self::USER_CACHE_LIFETIME_MINUTES * 60);
    }

    /**
     * Get the currently signed in user.
     *
     * @return mixed
     */
    public function getUserByAccessToken($accessToken = false)
    {
        if($accessTokenFromStorage = AccessTokenService::getAccessTokenFromStorage()){
            $AccessTokenInstance = AccessTokenService::ClassInstanceByToken($accessTokenFromStorage);
        }
        if(!isset($accessTokenFromStorage) && $accessToken){
            $AccessTokenInstance = AccessTokenService::ClassInstanceByToken($accessToken);
        }

        if(isset($AccessTokenInstance)  && $AccessTokenInstance instanceof AccessToken){
            return $this->getOAuthService()->getUser($AccessTokenInstance);
        }

        return false;
    }

    /**
     * Triggers the login flow by redirecting the user to the login Url
     * @param $state
     */
    public function triggerLoginFlow($state = null)
    {
        $options = [];
        if(isset($state)){
            $options = [
                'state' => $state
            ];
        }
        $repoClass = new CommonLoginRepository();
        RedirectHelper::redirect(
            $repoClass->getOAuthService()->getAuthorizationUrl($options)
        );
    }

    /**
     * Returns an instance of ServiceOauth
     *
     * @return CommonLoginOAuth
     */
    public function getOAuthService(){
        if (!$this->oAuthService) {
            $locale = Plugin::instance()->settings->get_current_locale();

            $this->oAuthService = new CommonLoginOAuth([
                'clientId' => Plugin::instance()->settings->get_api_user($locale),
                'clientSecret' => Plugin::instance()->settings->get_api_secret($locale),
                'scopes' => [],
            ], Plugin::instance()->settings);
        }

        return $this->oAuthService;
    }
}