<?php

namespace Bonnier\WP\OAuth\Helpers;


class RedirectHelper
{
    /**
     * Redirect the user to the provided path,
     * and disable as much cache as possible
     * and only make temporary redirects.
     *
     * @param $to
     */
    public static function redirect($to)
    {
        NoCacheHeader::set();
        header(sprintf('Location: %s', $to), true, 302);
        exit();
    }
}