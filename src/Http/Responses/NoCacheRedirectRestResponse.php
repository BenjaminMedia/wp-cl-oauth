<?php

namespace Bonnier\WP\OAuth\Http\Responses;

use WP_REST_Response;

class NoCacheRedirectRestResponse extends NoCacheRestResponse
{
    public function __construct(string $location)
    {
        parent::__construct(null, $status = 302, $headers = [
            'Location' => $location
        ]);
    }
}
