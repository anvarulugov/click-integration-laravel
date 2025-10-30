<?php

return [
    'provider' => [
        'endpoint' => env('CLICK_API_ENDPOINT', 'https://api.click.uz/v2/merchant/'),
        'default_service' => env('CLICK_DEFAULT_SERVICE', 'default'),
        'services' => [
            'default' => [
                'merchant_id' => env('CLICK_MERCHANT_ID', ''),
                'service_id' => env('CLICK_SERVICE_ID', ''),
                'user_id' => env('CLICK_USER_ID', ''),
                'secret_key' => env('CLICK_SECRET_KEY', ''),
            ],
            // 'secondary' => [
            //     'merchant_id' => env('CLICK_SECONDARY_MERCHANT_ID', ''),
            //     'service_id' => env('CLICK_SECONDARY_SERVICE_ID', ''),
            //     'user_id' => env('CLICK_SECONDARY_USER_ID', ''),
            //     'secret_key' => env('CLICK_SECONDARY_SECRET_KEY', ''),
            // ],
        ],
    ],
    'database' => [
        'connection' => env('CLICK_DB_CONNECTION'),
        'table' => env('CLICK_PAYMENTS_TABLE', 'payments'),
    ],
    'session' => [
        'header' => env('CLICK_SESSION_AUTH_HEADER', 'Auth'),
    ],
];
