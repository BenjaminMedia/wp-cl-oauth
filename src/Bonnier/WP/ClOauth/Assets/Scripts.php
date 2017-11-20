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

        wp_enqueue_script('bp-wa-oauth-login', $script_src, [], '1.3.4', true);
    }
}