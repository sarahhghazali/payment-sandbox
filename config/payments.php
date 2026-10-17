<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shared secret
    |--------------------------------------------------------------------------
    |
    | The gateway signs every callback with this secret and the merchant app
    | verifies it. In a real integration each merchant gets its own secret.
    |
    */
    'secret' => env('PAYMENTS_SECRET', 'local-development-secret'),

    /*
    |--------------------------------------------------------------------------
    | Callback tolerances
    |--------------------------------------------------------------------------
    |
    | How old a signed callback may be before it is rejected as a replay, and
    | how many times the gateway retries a callback the merchant did not
    | acknowledge with a 2xx response.
    |
    */
    'max_age_seconds' => (int) env('PAYMENTS_MAX_AGE_SECONDS', 300),

    'delivery' => [
        'max_attempts' => (int) env('PAYMENTS_DELIVERY_ATTEMPTS', 5),
        // Seconds to wait before attempt 2, 3, 4, 5 (exponential backoff).
        'backoff' => [10, 30, 120, 600],
        'timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Amounts are always stored and compared as integer minor units (cents),
    | never as floats.
    |
    */
    'currency' => env('PAYMENTS_CURRENCY', 'MYR'),

];
