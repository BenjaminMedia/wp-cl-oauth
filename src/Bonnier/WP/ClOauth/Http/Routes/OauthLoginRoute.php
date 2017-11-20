<?php

namespace Bonnier\WP\ClOauth\Http\Routes;

use Bonnier\WP\ClOauth\Helpers\Base64;
use Bonnier\WP\ClOauth\Helpers\RedirectHelper;
use Bonnier\WP\ClOauth\Http\Exceptions\HttpException;
use Bonnier\WP\ClOauth\Repository\CommonLoginRepository;
use Bonnier\WP\ClOauth\Services\AccessTokenService;
use Bonnier\WP\ClOauth\Services\CommonLoginOAuth;
use Bonnier\WP\ClOauth\Settings\SettingsPage;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Class OauthLoginRoute
 * @package Bonnier\WP\ClOauth\Http\Routes
 */
class OauthLoginRoute
{
    const BASE_PREFIX = 'wp-json';

    /**
     * The namespace prefix.
     */
    const PLUGIN_PREFIX = 'bp-cl-oauth';

    /**
     * The namespace version.
     */
    const VERSION = 'v1';

    /**
     * The login route.
     */
    const LOGIN_ROUTE = '/oauth/login';

    /**
     * The logout route.
     */
    const LOGOUT_ROUTE = '/oauth/logout';

    /**
     * Has access to article route.
     */
    const HAS_ACCESS_ROUTE = '/has-access';

    /**
     * The access token cookie lifetime.
     */
    const ACCESS_TOKEN_LIFETIME_HOURS = 24;

    /* @var SettingsPage $settings */
    private $settings;

    private $clRepo;

    /**
     * OauthLoginRoute constructor.
     * @param SettingsPage $settings
     */
    public function __construct(SettingsPage $settings)
    {
        $this->settings = $settings;
        $this->clRepo = new CommonLoginRepository();

        add_action('rest_api_init', function () {
            register_rest_route($this->get_route_namespace(), self::LOGIN_ROUTE, [
                'methods' => 'GET, POST',
                'callback' => [$this, 'login'],
            ]);
            register_rest_route($this->get_route_namespace(), self::LOGOUT_ROUTE, [
                'methods' => 'GET, POST',
                'callback' => [$this, 'logout'],
            ]);
            register_rest_route($this->get_route_namespace(), self::HAS_ACCESS_ROUTE, [
                'methods' => 'GET, POST',
                'callback' => [$this, 'has_access'],
            ]);
        });
    }

    /**
     * The function that handles the user login request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function login(WP_REST_Request $request)
    {
        header('Cache-Control: no-cache');

        $redirectUri = $request->get_param('redirectUri'); // Where should we return on successful login
        $state = $request->get_param('state'); // Login: 'undefined'
        $accessToken = $request->get_param('accessToken'); // Login: null
        $postRequiredRole = null;


        // Persist auth destination
        // Check if auth destination has been set
        if(!$redirect = $this->clRepo->getAuthDestination()){
            $redirect = $this->clRepo->setAuthDestination($redirectUri);
        }

        if(isset($accessToken) && !empty($accessToken)){
            AccessTokenService::setAccessTokenToStorage($accessToken);
            RedirectHelper::redirect($redirectUri);
        }

        // Get user from admin service
        try {
            $commonLoginUser = $this->clRepo->getUserFromLoginRequest($request);
            if (!$commonLoginUser) {
                $this->clRepo->triggerLoginFlow($state);
            }
        } catch (IdentityProviderException $exception) {
            $this->clRepo->triggerLoginFlow($state);
            //return new WP_REST_Response(['error' => $e->getMessage()], $e->getCode());
        }

        //TODO: kill this, so that login can work without purchase
        if($state){
            $state = json_decode(Base64::UrlDecode($state));
            if(isset($state->purchase)) {
                if($accessToken = AccessTokenService::getAccessTokenFromStorage()){
                    RedirectHelper::redirect($this->clRepo->getPaymentUrl($state->purchase, $state->product_url, $accessToken, $state->product_preview));
                }
            }
        }

        $redirect = $this->clRepo->getAuthDestination();

        if (!$redirect) {
            // Redirect to home page
            $redirect = home_url('/');
        }

        RedirectHelper::redirect($redirect);
    }

    /**
     * The function that handles the user logout request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function logout(WP_REST_Request $request)
    {
        header('Cache-Control: no-cache');

        AccessTokenService::destroyAccessTokenCookie();

        if($this->settings->get_create_local_user($this->settings->get_current_locale())) {
            wp_logout();
        }

        $redirectUri = $this->settings->get_api_endpoint().'logout?redirect_to='.urlencode($request->get_param('redirectUri'));

        if($redirectUri) {
            RedirectHelper::redirect($redirectUri);
        }

        return new WP_REST_Response('ok', 200);
    }

    public function has_access(WP_REST_Request $request)
    {
        header('Cache-Control: no-cache');

        $purchaseId = $request->get_param('uid');
        $postId = $request->get_param('pid');
        $data = json_decode($request->get_param('data'), true);

        if(!$this->clRepo->hasAccessTo($purchaseId) || !$data) {
            return new WP_REST_Response(['status' => 'No Access']);
        }

        $widgets = get_field('composite_content', $postId);
        $result = [];

        foreach($data as $key => $widgetBtn){
            $type = $widgets[$widgetBtn['data-index']]['acf_fc_layout'];

            if(isset($widgetBtn['data-index']) && $type === $widgetBtn['data-type']){
                switch ($type){
                    case 'file' :
                        $result[$key]['data-id'] = $widgetBtn['data-id'];
                        $result[$key]['data-disclaimer'] = $widgetBtn['data-disclaimer'] ?? '0';
                        $result[$key]['data-target'] = $widgetBtn['data-target'] ?? false;
                        $result[$key]['data-response'] = as3cf_get_secure_attachment_url($widgetBtn['data-id'], 3600);
                        break;
                    case 'video' :
                        $result[$key]['data-id'] = $widgetBtn['data-id'];
                        preg_match("/src=\"(.*?)\"/", $widgets[$widgetBtn['data-index']]['embed_url'], $matches);
                        if(!isset($matches[1])){
                            return;
                        }
                        $result[$key]['data-response'] = $matches[1];
                        $result[$key]['data-caption'] = $widgets[$widgetBtn['data-index']]["caption"];
                        break;
                    case 'link' :
                        $result[$key]['data-id'] = $widgetBtn['data-id'];
                        $result[$key]['data-response'] = $widgets[$widgetBtn['data-index']]['url'];
                        break;
                    default:
                        $result[$key]['data-id'] = $widgetBtn['data-id'];
                        $result[$key]['data-response'] = false;
                }
            }
        }

        if($result) {
            return new WP_REST_Response(['status' => 'OK', 'response' => $result]);
        }

        return new WP_REST_Response(['status' => 'No Access']);
    }

    /**
     * Returns the route namespace
     *
     * @return string
     */
    private function get_route_namespace()
    {
        return self::PLUGIN_PREFIX . '/' . self::VERSION;
    }
}
