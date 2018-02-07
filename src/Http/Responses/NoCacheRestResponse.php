<?php

namespace Bonnier\WP\OAuth\Http\Responses;

use WP_REST_Response;

class NoCacheRestResponse extends WP_REST_Response
{
    public function __construct(?mixed $data = null, int $status = 200, array $headers = array())
    {
        $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0';
        $headers['Expires'] = 'Sat, 1 Jan 2000 00:00:00 GMT';
        parent::__construct($data, $status, $headers);
    }
}