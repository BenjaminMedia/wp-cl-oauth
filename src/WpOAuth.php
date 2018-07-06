<?php

namespace Bonnier\WP\OAuth;

use Bonnier\WP\OAuth\Assets\Scripts;
use Bonnier\WP\OAuth\Http\Routes;
use Bonnier\WP\OAuth\Providers\CommonLoginProvider;
use Bonnier\WP\OAuth\Repositories\UserRepository;
use Bonnier\WP\OAuth\Settings\SettingsPage;
use League\OAuth2\Client\Provider\AbstractProvider;

class WpOAuth
{
    /** Text domain for translators */
    const TEXT_DOMAIN = 'bp-cl-oauth';

    /** @var object Instance of this class */
    private static $instance;

    /** @var SettingsPage */
    private $settings;

    /** @var string Directory of this class */
    private $dir;

    /** @var string Basename of this class */
    private $basename;

    /** @var string Plugins directory for this plugin */
    private $plugin_dir;

    /** @var string Plugins url for this plugin */
    private $plugin_url;

    /** @var UserRepository */
    private $userRepo;

    /** @var CommonLoginProvider */
    private $oauthProvider;

    /** @var Routes */
    private $routes;

    /**
     * Do not load this more than once.
     */
    private function __construct()
    {
        // Set plugin file variables
        $this->dir = __DIR__;
        $this->basename = plugin_basename($this->dir);
        $this->plugin_dir = plugin_dir_path($this->dir);
        $this->plugin_url = plugin_dir_url($this->dir);

        // Load textdomain
        load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname($this->basename) . '/languages');
    }

    private function bootstrap()
    {
        $this->routes = new Routes();
        $this->settings = new SettingsPage();
        $this->userRepo = new UserRepository();
        $this->oauthProvider = new CommonLoginProvider();

        Scripts::bootstrap();

        if (!session_id()) {
            session_start();
        }
    }

    /**
     * Returns the instance of this class.
     */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self;
            global $bp_cl_oauth;
            $bp_cl_oauth = self::$instance;
            self::$instance->bootstrap();

            /**
             * Run after the plugin has been loaded.
             */
            do_action('bp_cl_oauth_loaded');
        }

        return self::$instance;
    }

    /**
     * @return SettingsPage
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * @return UserRepository
     */
    public function getUserRepo()
    {
        return $this->userRepo;
    }

    /**
     * @return CommonLoginProvider
     */
    public function getOauthProvider()
    {
        return $this->oauthProvider;
    }

    /**
     * @return Routes
     */
    public function getRoutes()
    {
        return $this->routes;
    }
    
    /**
     * @param SettingsPage $settings
     */
    public function setSettings(SettingsPage $settings): void
    {
        $this->settings = $settings;
    }
    
    /**
     * @param UserRepository $userRepo
     */
    public function setUserRepo(UserRepository $userRepo): void
    {
        $this->userRepo = $userRepo;
    }
    
    /**
     * @param AbstractProvider $oauthProvider
     */
    public function setOauthProvider(AbstractProvider $oauthProvider): void
    {
        $this->oauthProvider = $oauthProvider;
    }
    
    /**
     * @param Routes $routes
     */
    public function setRoutes(Routes $routes): void
    {
        $this->routes = $routes;
    }

    /**
     * @return string
     */
    public function getPluginUrl()
    {
        return $this->plugin_url;
    }
}
