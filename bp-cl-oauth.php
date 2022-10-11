<?php
/**
 * Plugin Name: Bonnier OAuth Plugin
 * Version: 2.3.4
 * Plugin URI: https://github.com/BenjaminMedia/wp-cl-oauth
 * Description: This plugin allows you to integrate your site with an OAuth2 service
 * Author: Bonnier
 * License: GPL v3
 */

// Do not access this file directly
if (!defined('ABSPATH')) {
    exit;
}

require_once (__DIR__ . '/vendor/autoload.php');

require_once (__DIR__ . '/src/api.php');

spl_autoload_register(function ($className) {
    $namespace = 'Bonnier\\WP\\OAuth\\';
    if (str_contains($className, $namespace)) {
        // Convert `Bonnier\WP\OAuth\Providers\CommonLoginProvider`
        // to `__DIR__/src/Providers/CommonLoginProvider`
        $className = str_replace([$namespace, '\\'], [__DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], $className);

        $file = $className . '.php';

        if(file_exists($file)) {
            require_once($className . '.php');
        } else {
            throw new Exception(sprintf('\'%s\' does not exist!', $file));
        }
    }
});

function register_bp_cl_oauth()
{
    return \Bonnier\WP\OAuth\WpOAuth::instance();
}

add_action('plugins_loaded', 'register_bp_cl_oauth', 10);
