<?php

namespace Bonnier\WP\ClOauth\Assets;

use Bonnier\WP\ClOauth;

class Scripts
{
    public static function bootstrap()
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_login_script']);
    }

    public static function enqueue_login_script()
    {
        $plugin = ClOauth\instance();

        $script_src = $plugin->plugin_url . 'js/bp-cl-oauth-login.js';

        wp_register_script('bp-wa-oauth-login', $script_src, [], '1.3.7', true);
        wp_localize_script('bp-wa-oauth-login', 'translations', ['current_language' => pll__('Download Image')]);
        wp_enqueue_script('bp-wa-oauth-login');
    }
}