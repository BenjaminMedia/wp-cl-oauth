<?php

namespace Bonnier\WP\OAuth\Helpers;

class RedirectHelper
{
    /**
     * Redirect the user to the provided path,
     * and disable as much cache as possible
     * and only make temporary redirects.
     *
     * @param $toLocation
     */
    public static function redirect($toLocation)
    {
        NoCacheHeader::set();
        header(sprintf('Location: %s', $toLocation), true, 302);
        exit();
    }
}
