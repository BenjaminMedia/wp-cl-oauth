<?php

namespace Bonnier\WP\OAuth\Assets;

use Bonnier\WP\OAuth\WpOAuth;

class Scripts
{
    public static function bootstrap()
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_login_script']);
    }

    public static function enqueue_login_script()
    {
        $script_src = trim(WpOAuth::instance()->getPluginUrl(), '/') . '/assets/js/bp-cl-oauth-login.js';
        wp_register_script('bp-wa-oauth-login', $script_src, [], '2.0.13', true);
        wp_localize_script('bp-wa-oauth-login', 'routes', [
            'login' => WpOAuth::instance()->getRoutes()->getLoginRoute(),
            'logout' => WpOAuth::instance()->getRoutes()->getLogoutRoute()
        ]);
        wp_enqueue_script('bp-wa-oauth-login');
    }
}
