<?php

/**
 * Returns an instance of the bp-wa-oauth plugin
 *
 * @return \Bonnier\WP\ClOauth\Plugin|null
 */
function bp_cl_oauth()
{
    return isset($GLOBALS['bp_cl_oauth']) ? $GLOBALS['bp_cl_oauth'] : null;
}