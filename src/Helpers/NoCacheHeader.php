<?php


namespace Bonnier\WP\OAuth\Helpers;


class NoCacheHeader
{
    public static function set()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        header('Expires: Sat, 1 Jan 2000 00:00:00 GMT');
    }
}