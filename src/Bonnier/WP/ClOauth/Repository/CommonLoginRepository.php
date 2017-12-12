<?php
namespace Bonnier\WP\ClOauth\Repository;

use Bonnier\WP\ClOauth\Helpers\Base64;
use Bonnier\WP\ClOauth\Helpers\RedirectHelper;
use Bonnier\WP\ClOauth\Http\Client;
use Bonnier\WP\ClOauth\Http\Exceptions\HttpException;
use Bonnier\WP\ClOauth\Http\Routes\OauthLoginRoute;
use Bonnier\WP\ClOauth\Services\AccessTokenService;
use Bonnier\WP\ClOauth\Services\CommonLoginOAuth;
use Bonnier\WP\ClOauth\WpClOAuth;
use League\OAuth2\Client\Token\AccessToken;

class CommonLoginRepository
{
    protected $oAuthService;

    /**
     * The auth destination cookie key.
     */
    const AUTH_DESTINATION_COOKIE_KEY = 'bp_cl_oauth_auth_destination';

    private $user;
    
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
                return CommonLoginRepository::getUserByAccessToken(AccessTokenService::setAccessTokenToStorage($accessToken->getToken()));
            }
            catch(\Exception $exception) {
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
     * @param bool $accessToken
     * @return mixed
     */
    public function getUser($accessToken = false)
    {
        if($this->user) {
            return $this->user;
        }
        if(!isset($accessToken)) {
            $accessToken = AccessTokenService::getAccessTokenFromStorage();
        }

        if($user = $this->getUserByAccessToken($accessToken)){
            $this->user = $user;
            return $user;
        }

        if($user = $this->getOAuthService()->getUser($accessToken)){
            $this->user = $user;
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
        return isset($_COOKIE[AccessTokenService::ACCESS_TOKEN_COOKIE_KEY]) && $_COOKIE[AccessTokenService::ACCESS_TOKEN_COOKIE_KEY];
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
        $plugin = WpClOAuth::instance();
        $wpSiteManager = \WpSiteManager\Plugin::instance();
        $client = new Client([
            'base_uri' => $plugin->settings->get_purchase_manager_url($plugin->settings->get_current_locale()),
        ]);
        if($accessToken = AccessTokenService::getAccessTokenFromStorage()){
            try{
                $response = $client->get('has_access',[
                    'body' => [
                        'access_token' => $accessToken->getToken(),
                        'product_id' => $productId,
                        'callback' => $callbackUrl,
                        'site_id' => $wpSiteManager->settings()->getSiteId($wpSiteManager->settings()->getCurrentLocale()),
                    ],
                    'headers' => [
                        'Accept' => 'application/json'
                    ]
                ]);
            }
            catch(HttpException $e){
                return false;
            }

            if($response && 200 == $response->getStatusCode()){
                return true;
            }
        }

        return false;

    }

    /**
     * @param $productId
     * @param bool|false $callbackUrl // Article urL
     * @param bool|false $accessToken
     * @return string
     */
    public function getPaymentUrl($productId, $callbackUrl = false, $accessToken = false, $paymentPreviewAttributes) {
        if(!$callbackUrl){
            $callbackUrl = home_url('/');
        }
        $plugin = WpClOAuth::instance();
        $locale = $plugin->settings->get_current_locale();
        if(!$accessToken){
            $accessToken = AccessTokenService::getAccessTokenFromStorage();
            if(!$this->isAuthenticated() || !$accessToken){
                return $callbackUrl;
            }
        }
        if($accessToken instanceof AccessToken) {
            $accessToken->getToken();
        }
        return $plugin->settings->get_purchase_manager_url().
            'has_access?access_token='.urlencode($accessToken).
            '&product_id='.urlencode($productId).
            '&callback='.urlencode($callbackUrl).
            '&site_id='.wpSiteManager()->settings()->getSiteId(get_locale()).
            $this->paymentPreviewParameters($paymentPreviewAttributes);
    }

    public static function paymentPreviewParameters($paymentArticlePreviewAttributes){
        $attributes = '';
        foreach($paymentArticlePreviewAttributes as $key => $attribute){
            $attributes .= '&'.$key.'='. urlencode($attribute);
        }
        if(!empty($attributes)){
            return $attributes;
        }
        return false;
    }

    /**
     * Get the currently signed in user.
     *
     * @param bool $accessToken
     * @return mixed
     */
    public function getUserByAccessToken($accessToken = false)
    {
        $accessTokenFromStorage = AccessTokenService::getAccessTokenFromStorage();
        if($accessTokenFromStorage && $accessTokenFromStorage->getToken() && is_string($accessTokenFromStorage->getToken())){
            $AccessTokenInstance = AccessTokenService::ClassInstanceByToken($accessTokenFromStorage);
        } else {
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
    public function triggerLoginFlow($state = false, $redirectUri = false)
    {
        $options = [];
        if(isset($state) && !empty($state)){
            $options['state'] = $state;
        }

        if(!empty($redirectUri)){
            $options['redirect_uri'] = $redirectUri;
        }
        RedirectHelper::redirect(
            $this->getOAuthService()->getAuthorizationUrl($options)
        );
    }

    /**
     * Returns an instance of ServiceOauth
     *
     * @return CommonLoginOAuth
     */
    public function getOAuthService(){
        if (!$this->oAuthService) {
            $locale = WpClOAuth::instance()->settings->get_current_locale();

            $this->oAuthService = new CommonLoginOAuth([
                'clientId' => WpClOAuth::instance()->settings->get_api_user($locale),
                'clientSecret' => WpClOAuth::instance()->settings->get_api_secret($locale),
                'scopes' => [],
            ]);
        }

        return $this->oAuthService;
    }

    /**
     * Persist the auth destination in a cookie
     *
     * @param $destination
     */
    public function setAuthDestination($destination)
    {
        setcookie(self::AUTH_DESTINATION_COOKIE_KEY, $destination, time() + (1 * 60 * 60), '/');
        return $destination;
    }

    /**
     * Get the auth destination from the cookie
     *
     * @return bool
     */
    public function getAuthDestination()
    {
        return isset($_COOKIE[self::AUTH_DESTINATION_COOKIE_KEY]) ? $_COOKIE[self::AUTH_DESTINATION_COOKIE_KEY] : false;
    }
}
