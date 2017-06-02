<?php

namespace Bonnier\WP\ClOauth\Services;

use Bonnier\WP\ClOauth;
use Bonnier\WP\ClOauth\Settings\SettingsPage;
use \League\OAuth2\Client\Provider\AbstractProvider;
use \League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ResponseInterface;

class CommonLoginOAuth extends AbstractProvider
{
    private $pluginInstance;
    private $baseAuthorizationUrl;
    private $accessToken;
    private $user;
    protected $clientId;
    protected $appSecret;

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
        if(isset($options['clientId'])){
            $this->clientId = $options['clientId'];
        }

        if(isset($options['clientSecret'])){
            $this->clientSecret = $options['clientSecret'];
        }

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

    /**
     * Get the currently signed in user.
     *
     * @return mixed
     * @throws Exception
     */
    public function getUser()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        if ($this->accessToken) {

            $response = $this->get('api/users/current.json', ['headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken
            ]]);

            if ($response->getStatusCode() == 200) {
                $this->user = json_decode($response->getBody());
            }
        }

        return $this->user;
    }

    public function getResourceOwnerDetailsUrl(AccessToken $token){

    }

    protected function getDefaultScopes()
    {
        // TODO: Implement getDefaultScopes() method.
    }

    protected function checkResponse(ResponseInterface $response, $data)
    {
        // TODO: Implement checkResponse() method.
    }

    protected function createResourceOwner(array $response, AccessToken $token)
    {
        // TODO: Implement createResourceOwner() method.
    }
}