<?php

namespace Bonnier\WP\ClOauth\Services;

use Bonnier\WP\ClOauth;
use Bonnier\WP\ClOauth\Plugin;
use Bonnier\WP\ClOauth\Settings\SettingsPage;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use \League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;

class CommonLoginOAuth extends AbstractProvider
{
    use BearerAuthorizationTrait;

    private $pluginInstance;
    private $baseAuthorizationUrl;
    private $accessToken;
    protected $scopes = [ 'user_read' ];
    private $responseError = 'error';
    private $responseCode;
    private $responseResourceOwnerId;
    protected $clientId;
    protected $appSecret;

    const USER_IDENTIFIER = 'id';

    /**
     * @return mixed
     */
    public function getAppSecret()
    {
        return $this->appSecret;
    }

    /**
     * @param mixed $appSecret
     */
    public function setAppSecret($appSecret)
    {
        $this->appSecret = $appSecret;
    }

    /**
     * @return mixed
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @param mixed $clientId
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }

    /**
     * CommonLoginOAuth constructor.
     * @param $pluginInstance
     */
    public function __construct(array $options = [], $settings)
    {

        $this->pluginInstance = ClOauth\instance();

        $this->setBaseAuthorizationUrl($this->pluginInstance->settings->get_api_endpoint($settings->get_current_locale()));
        parent::__construct($options);
    }

    /**
     * Set the access token
     *
     * @param $token
     */
    public function setAccessToken($token)
    {
        $this->accessToken = $token;
    }

    public function getCurrentAccessToken()
    {
        if($token = isset($this->accessToken)) {
            return AccessTokenService::ClassInstanceByToken($token);
        }

        return false;
    }

    /**
     * @param mixed $baseAuthorizationUrl
     */
    public function setBaseAuthorizationUrl($baseAuthorizationUrl)
    {
        $this->baseAuthorizationUrl = $baseAuthorizationUrl;
    }

    /**
     * @return mixed
     */
    public function getPluginInstance()
    {
        return $this->pluginInstance;
    }

    /**
     * @param mixed $pluginInstance
     */
    public function setPluginInstance($pluginInstance)
    {
        $this->pluginInstance = $pluginInstance;
    }

    /**
     * @return null
     */
    public function getBaseAuthorizationUrl()
    {
        return $this->baseAuthorizationUrl.'authorize';
    }

    public function getBaseAccessTokenUrl(array $params){
        return $this->baseAuthorizationUrl.'token';
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token){
        return $this->baseAuthorizationUrl.'user';
    }

    /**
     * Get the currently signed in user.
     *
     * @return mixed
     * @throws Exception
     */
    public function getUser($accessToken = false)
    {
        if ($this->user !== null) {
            return $this->user;
        }
        if($accessTokenFromStorage = AccessTokenService::getAccessTokenFromStorage()){
            $this->user = $this->service->getResourceOwner($accessTokenFromStorage);
            return $this->user;
        }
        if(isset($accessToken)){
            $this->user = $this->service->getResourceOwner($accessToken);
            return $this->user;
        }

        return false;
    }

    protected function getDefaultScopes()
    {
        return $this->scopes;
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        if (!empty($data[$this->responseError])) {
            $error = $data[$this->responseError];
            $code  = $this->responseCode ? $data[$this->responseCode] : 0;
            throw new IdentityProviderException($error, $code, $data);
        }
    }

    public function createResourceOwner(array $response, AccessToken $token)
    {
        $this->responseResourceOwnerId = $response[self::USER_IDENTIFIER];
        $this->user = new \stdClass();
        foreach($response as $property => $value) {
            $this->user->$property = $value;
        }

        return $this->user;
    }
}