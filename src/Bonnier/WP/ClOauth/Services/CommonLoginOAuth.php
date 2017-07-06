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

    private $instance;
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
    public function __construct(array $options = [])
    {

        $this->instance = ClOauth\instance();

        $this->setBaseAuthorizationUrl(Plugin::instance()->settings->get_api_endpoint(Plugin::instance()->settings->get_current_locale()));
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
        if(!empty($accessToken)){
            try {
                return $this->getResourceOwner($accessToken);
            }
            catch (IdentityProviderException $exception) {
                return false;
            }
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

    /**
     * Returns the default headers used by this provider.
     *
     * Typically this is used to set 'Accept' or 'Content-Type' headers.
     *
     * @return array
     */
    protected function getDefaultHeaders()
    {
        return [
            'Accept' => 'application/json'
        ];
    }
}