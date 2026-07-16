<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Plunk API Key
    |--------------------------------------------------------------------------
    |
    | Secret key from your Plunk dashboard (cloud or self-hosted).
    | Can also be set at runtime from Settings → Mail.
    |
    */
    'api_key' => env('PLUNK_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Plunk API URL
    |--------------------------------------------------------------------------
    |
    | Base URL for your Plunk instance. Self-hosted example:
    | https://plunk.yourdomain.com
    |
    */
    'api_url' => env('PLUNK_API_URL', 'https://api.useplunk.com'),

    /*
    |--------------------------------------------------------------------------
    | Plunk API Endpoint
    |--------------------------------------------------------------------------
    |
    | Send endpoint path. Official Plunk cloud uses /v1/send.
    | Self-hosted Plunk typically uses /api/v1/send.
    |
    */
    'endpoint' => env('PLUNK_API_ENDPOINT', '/v1/send'),

];
