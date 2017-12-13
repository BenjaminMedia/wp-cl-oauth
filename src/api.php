<?php

/**
 * Returns an instance of the bp-oauth plugin
 *
 * @return \Bonnier\WP\OAuth\WpOAuth|null
 */
function bp_cl_oauth()
{
    return isset($GLOBALS['bp_cl_oauth']) ? $GLOBALS['bp_cl_oauth'] : null;
}