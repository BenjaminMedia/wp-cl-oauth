<?php

namespace Bonnier\WP\OAuth\Providers;

use Bonnier\WP\OAuth\Services\AccessTokenService;
use Bonnier\WP\OAuth\WpOAuth;
use GuzzleHttp\Exception\ClientException;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Tool\BearerAuthorizationTrait;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

class CommonLoginProvider extends AbstractProvider
{
    use BearerAuthorizationTrait;

    private $endpoint;

    public function __construct()
    {
        $settings = WpOAuth::instance()->getSettings();
        $this->endpoint = trim($settings->get_api_endpoint(), '/');

        parent::__construct([
            'clientId' => $settings->get_api_user(),
            'clientSecret' => $settings->get_api_secret(),
            'redirectUri' => $this->getRedirectUri(),
        ]);
    }
    
    /**
     * @return array
     */
    protected function getDefaultHeaders()
    {
        return [
            'Accept' => 'application/json',
        ];
    }
    
    /**
     * Returns the base URL for authorizing a client.
     *
     * Eg. https://oauth.service.com/authorize
     *
     * @return string
     */
    public function getBaseAuthorizationUrl()
    {
        return sprintf('%s/authorize', $this->endpoint);
    }

    public function getLogoutUrl($redirect_uri)
    {
        return sprintf('%s/logout?redirect_to=%s', $this->endpoint, urlencode($redirect_uri));
    }

    public function getEditUrl($redirect_uri)
    {
        return sprintf(
            '%s/user/edit?access_token=%s&callbackurl=%s',
            preg_replace('#/oauth$#', '', $this->endpoint), /* remove /oauth from end of string */
            urlencode(AccessTokenService::getFromStorage()->getToken() ?? null),
            urlencode($redirect_uri)
        );
    }

    public function getDeleteUrl()
    {
        return sprintf(
            '%s/user/delete?access_token=%s',
            preg_replace('#/oauth$#', '', $this->endpoint), /* remove /oauth from end of string */
            urlencode(AccessTokenService::getFromStorage()->getToken() ?? null)
        );
    }

    /**
     * Returns the base URL for requesting an access token.
     *
     * Eg. https://oauth.service.com/token
     *
     * @param array $params
     * @return string
     */
    public function getBaseAccessTokenUrl(array $params)
    {
        return sprintf('%s/token', $this->endpoint);
    }
    
    /**
     * @param AccessToken $token
     * @return ResourceOwnerInterface|null
     */
    public function getResourceOwner(AccessToken $token)
    {
        try {
            return parent::getResourceOwner($token);
        } catch (UnexpectedValueException $e) {
            return null;
        }
    }
    
    /**
     * Returns the URL for requesting the resource owner's details.
     *
     * @param AccessToken $token
     * @return string
     */
    public function getResourceOwnerDetailsUrl(AccessToken $token)
    {
        return sprintf('%s/user', $this->endpoint);
    }

    /**
     * Returns the default scopes used by this provider.
     *
     * This should only be the scopes that are required to request the details
     * of the resource owner, rather than all the available scopes.
     *
     * @return array
     */
    protected function getDefaultScopes()
    {
        return [];
    }
    
    public function updateSubscriptionNumber($subscriptionNumber)
    {
        $request = $this->getAuthenticatedRequest(
            'post',
            sprintf('%s/user/subscription_number', $this->endpoint),
            AccessTokenService::getFromStorage()
        );
        try {
            $response = $this->getHttpClient()->send($request, [
                'form_params' => ['subscription_number' => $subscriptionNumber]
            ]);
        } catch (ClientException $e) {
            return json_decode($e->getResponse()->getBody()->getContents());
        }
        return json_decode($response->getBody()->getContents());
    }

    /**
     * Checks a provider response for errors.
     *
     * @throws IdentityProviderException
     * @param  ResponseInterface $response
     * @param  array|string $data Parsed response data
     * @return void
     */
    protected function checkResponse(ResponseInterface $response, $data)
    {
        // Do not throw the exception if logged in editor / administrator to avoid error when previewing post
        if (isset($data['error']) && !current_user_can('editor') && !current_user_can('administrator')) {
            throw new IdentityProviderException(
                $data['error'] ?: $response->getReasonPhrase(),
                $response->getStatusCode(),
                $response
            );
        }
    }

    /**
     * Generates a resource owner object from a successful resource owner
     * details request.
     *
     * @param  array $response
     * @param  AccessToken $token
     * @return ResourceOwnerInterface
     */
    protected function createResourceOwner(array $response, AccessToken $token)
    {
        return new CommonLoginResourceOwner($response);
    }

    private function getRedirectUri()
    {
        if (function_exists('pll_home_url')) {
            $homeUrl = pll_home_url();
        } else {
            $homeUrl = home_url('/');
        }

        return rtrim($homeUrl, '/') . WpOAuth::instance()->getRoutes()->getCallbackRoute();
    }
}
