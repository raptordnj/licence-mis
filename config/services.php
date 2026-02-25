<?php

declare(strict_types=1);

return [
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

    'envato' => [
        'base_url' => env('ENVATO_API_BASE_URL', 'https://api.envato.com/v3/market'),
        'token' => env('ENVATO_API_TOKEN'),
        'cache_ttl_seconds' => (int) env('ENVATO_CACHE_TTL_SECONDS', 300),
        'mock_mode' => (bool) env('ENVATO_MOCK_MODE', false),
    ],

    'license' => [
        'hmac_key' => env('LICENSE_HMAC_KEY', ''),
        'verify_rate_limit' => (int) env('LICENSE_VERIFY_RATE_LIMIT', 30),
    ],

    'admin_auth' => [
        'max_attempts' => (int) env('ADMIN_AUTH_MAX_ATTEMPTS', 5),
        'lockout_seconds' => (int) env('ADMIN_AUTH_LOCKOUT_SECONDS', 900),
    ],

    'external_license_api' => [
        'url' => env('EXTERNAL_LICENSE_API_URL', ''),
        'key' => env('EXTERNAL_LICENSE_API_KEY', ''),
        'enabled' => (bool) env('EXTERNAL_LICENSE_API_ENABLED', false),
        'fallback_to_external' => (bool) env('EXTERNAL_LICENSE_API_FALLBACK', true),
    ],
];
