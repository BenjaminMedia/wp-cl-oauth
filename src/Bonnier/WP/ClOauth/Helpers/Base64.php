<?php

namespace Bonnier\WP\ClOauth\Helpers;


class Base64
{
    public static function UrlEncode($inputStr)
    {
        return strtr(base64_encode($inputStr), '+/=', '-_,');
    }

    public static function UrlDecode($inputStr)
    {
        return base64_decode(strtr($inputStr, '-_,', '+/='));
    }
}