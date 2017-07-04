<?php

namespace Bonnier\WP\ClOauth\Helpers;


class RedirectHelper
{
    /**
     * Redirect the user to provided path
     *
     * @param $to
     */
    public static function redirect($to)
    {
        header("Location: " . $to);
        exit();
    }
}