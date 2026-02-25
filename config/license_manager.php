<?php

declare(strict_types=1);

return [
    'jwt' => [
        'private_key_path' => env('LICENSE_JWT_PRIVATE_KEY', storage_path('app/keys/license-private.pem')),
        'public_key_path' => env('LICENSE_JWT_PUBLIC_KEY', storage_path('app/keys/license-public.pem')),
        'key_id' => env('LICENSE_JWT_KEY_ID', 'license-v1'),
        'issuer' => env('LICENSE_JWT_ISSUER', env('APP_URL', 'http://localhost')),
    ],

    'envato_mock' => [
        'mode' => (bool) env('LICENSE_ENVATO_MOCK_MODE', false),
        'allowed_prefixes' => array_values(array_filter(array_map(
            static fn (string $prefix): string => trim($prefix),
            explode(',', (string) env('LICENSE_ENVATO_MOCK_ALLOWED_PREFIXES', 'MOCK-,TEST-')),
        ), static fn (string $prefix): bool => $prefix !== '')),
        'seed' => env('LICENSE_ENVATO_MOCK_SEED'),
        'fixture_path' => env('LICENSE_ENVATO_MOCK_FIXTURE_PATH', storage_path('app/envato-mock/fixtures.json')),
        'allowed_item_ids' => array_values(array_filter(array_map(
            static function (string $itemId): ?int {
                $trimmed = trim($itemId);

                if ($trimmed === '' || ! ctype_digit($trimmed)) {
                    return null;
                }

                $parsed = (int) $trimmed;

                return $parsed > 0 ? $parsed : null;
            },
            explode(',', (string) env('LICENSE_ENVATO_MOCK_ALLOWED_ITEM_IDS', '')),
        ))),
        'allowed_product_ids' => array_values(array_filter(array_map(
            static function (string $productId): ?int {
                $trimmed = trim($productId);

                if ($trimmed === '' || ! ctype_digit($trimmed)) {
                    return null;
                }

                $parsed = (int) $trimmed;

                return $parsed > 0 ? $parsed : null;
            },
            explode(',', (string) env('LICENSE_ENVATO_MOCK_ALLOWED_PRODUCT_IDS', '')),
        ))),
        'fail_closed_in_prod' => (bool) env('LICENSE_ENVATO_MOCK_FAIL_CLOSED_IN_PROD', true),
    ],

    'token_ttl_seconds' => (int) env('LICENSE_TOKEN_TTL_SECONDS', 3600),
    'invalid_token_ttl_seconds' => (int) env('LICENSE_INVALID_TOKEN_TTL_SECONDS', 300),
    'verify_rate_limit_per_minute' => (int) env('LICENSE_PUBLIC_VERIFY_RATE_LIMIT', 60),
    'deactivate_rate_limit_per_minute' => (int) env('LICENSE_PUBLIC_DEACTIVATE_RATE_LIMIT', 30),
    'normalize_www' => (bool) env('LICENSE_NORMALIZE_WWW', true),
];
