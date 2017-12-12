<?php
/**
 * Plugin Name: Bonnier Common Login OAuth
 * Version: 1.3.8
 * Plugin URI: https://github.com/BenjaminMedia/wp-cl-oauth
 * Description: This plugin allows you to integrate your site with the whitealbum oauth user api
 * Author: Bonnier - Frederik Rabøl
 * License: GPL v3
 */

namespace Bonnier\WP\ClOauth;

use Bonnier\WP\ClOauth\Assets\Scripts;
use Bonnier\WP\ClOauth\Http\Routes\OauthLoginRoute;
use Bonnier\WP\ClOauth\Http\Routes\UserUpdateCallbackRoute;
use Bonnier\WP\ClOauth\Repository\CommonLoginRepository;
use Bonnier\WP\ClOauth\Settings\SettingsPage;

// Do not access this file directly
if (!defined('ABSPATH')) {
    exit;
}
require_once (__DIR__.'/includes/vendor/autoload.php');
// Handle autoload so we can use namespaces
spl_autoload_register(function ($className) {
    if (strpos($className, __NAMESPACE__) !== false) {
        $className = str_replace("\\", DIRECTORY_SEPARATOR, $className);
        require_once(__DIR__ . DIRECTORY_SEPARATOR . WpClOAuth::CLASS_DIR . DIRECTORY_SEPARATOR . $className . '.php');
    }
});

// Load plugin api
require_once (__DIR__ . '/'.WpClOAuth::CLASS_DIR.'/api.php');

class WpClOAuth
{
    /**
     * Text domain for translators
     */
    const TEXT_DOMAIN = 'bp-cl-oauth';

    const CLASS_DIR = 'src';

    /**
     * @var object Instance of this class.
     */
    private static $instance;

    public $settings;

    private $loginRoute;

    /**
     * @var string Filename of this class.
     */
    public $file;

    /**
     * @var string Basename of this class.
     */
    public $basename;

    /**
     * @var string Plugins directory for this plugin.
     */
    public $plugin_dir;

    /**
     * @var string Plugins url for this plugin.
     */
    public $plugin_url;

    private $clRepo;

    /**
     * Do not load this more than once.
     */
    private function __construct()
    {
        // Set plugin file variables
        $this->file = __FILE__;
        $this->basename = plugin_basename($this->file);
        $this->plugin_dir = plugin_dir_path($this->file);
        $this->plugin_url = plugin_dir_url($this->file);

        // Load textdomain
        load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname($this->basename) . '/languages');

        $this->settings = new SettingsPage();
        $this->loginRoute = new OauthLoginRoute($this->settings);
        new UserUpdateCallbackRoute($this->settings);
    }

    private function bootstrap() {
        Scripts::bootstrap();
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

    public function is_authenticated() {
        if(!$this->clRepo) {
            $this->clRepo = new CommonLoginRepository();
        }
        return $this->clRepo->isAuthenticated();
    }

    public function has_access($productId, $callbackUrl){
        if(!$this->clRepo) {
            $this->clRepo = new CommonLoginRepository();
        }
        return $this->clRepo->hasAccessTo($productId, $callbackUrl);
    }

    public function get_payment_url($productId, $callbackUrl){
        if(!$this->clRepo) {
            $this->clRepo = new CommonLoginRepository();
        }
        return $this->clRepo->getPaymentUrl($productId, $callbackUrl);
    }

    public function get_user() {
        if(!$this->clRepo) {
            $this->clRepo = new CommonLoginRepository();
        }
        return $this->clRepo->getUser();
    }

}

/**
 * @return WpClOAuth $instance returns an instance of the plugin
 */
function instance()
{
    return WpClOAuth::instance();
}

add_action('plugins_loaded', __NAMESPACE__ . '\instance', 0);
