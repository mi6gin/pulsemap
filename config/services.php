<?php

return [

    'yandex_maps' => [
        'connect_timeout' => (int) env('YANDEX_MAPS_CONNECT_TIMEOUT', 10),
        'timeout' => (int) env('YANDEX_MAPS_TIMEOUT', 25),
        'page_size' => (int) env('YANDEX_MAPS_PAGE_SIZE', 50),
        'max_pages' => (int) env('YANDEX_MAPS_MAX_PAGES', 20),
        'delay_min_ms' => (int) env('YANDEX_MAPS_DELAY_MIN_MS', 250),
        'delay_max_ms' => (int) env('YANDEX_MAPS_DELAY_MAX_MS', 650),
        'requests_per_minute' => (int) env('YANDEX_MAPS_REQUESTS_PER_MINUTE', 12),
        'proxy' => env('YANDEX_MAPS_PROXY'),
        'user_agents' => [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
