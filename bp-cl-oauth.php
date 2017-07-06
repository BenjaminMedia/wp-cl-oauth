<?php
/**
 * Plugin Name: Bonnier Common Login OAuth
 * Version: 0.1
 * Plugin URI: https://github.com/BenjaminMedia/wp-wa-oauth
 * Description: This plugin allows you to integrate your site with the whitealbum oauth user api
 * Author: Bonnier - Frederik Rabøl
 * License: GPL v3
 */

namespace Bonnier\WP\ClOauth;

use Bonnier\WP\ClOauth\Assets\Scripts;
use Bonnier\WP\ClOauth\Http\Routes\OauthLoginRoute;
use Bonnier\WP\ClOauth\Http\Routes\UserUpdateCallbackRoute;
use Bonnier\WP\ClOauth\Models\User;
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
        require_once(__DIR__ . DIRECTORY_SEPARATOR . Plugin::CLASS_DIR . DIRECTORY_SEPARATOR . $className . '.php');
    }
});

// Load plugin api
require_once (__DIR__ . '/'.Plugin::CLASS_DIR.'/api.php');

class Plugin
{
    /**
     * Text domain for translators
     */
    const TEXT_DOMAIN = 'bp-cl-oauth';

    const PURCHASE_MANAGER_URL = 'http://purchasemanager.bonnier.cloud/';

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
        $repoClass = new CommonLoginRepository();
        return $repoClass->isAuthenticated();
    }

    public function has_access($productId, $callbackUrl){
        $repoClass = new CommonLoginRepository();
        return $repoClass->hasAccessTo($productId, $callbackUrl);
    }

    public function get_payment_url($productId, $callbackUrl){
        $repoClass = new CommonLoginRepository();
        return $repoClass->getPaymentUrl($productId, $callbackUrl);
    }

    public function get_user() {
        $repoClass = new CommonLoginRepository();
        return $repoClass->getUser();
    }

}

/**
 * @return Plugin $instance returns an instance of the plugin
 */
function instance()
{
    return Plugin::instance();
}

add_action('plugins_loaded', __NAMESPACE__ . '\instance', 0);
