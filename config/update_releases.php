<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('UPDATE_RELEASES_ENABLED', true),

    'default_channel' => (string) env('UPDATE_RELEASES_DEFAULT_CHANNEL', 'stable'),

    'default_product_id' => env('UPDATE_RELEASES_DEFAULT_PRODUCT_ID'),

    'package_disk' => (string) env('UPDATE_RELEASES_PACKAGE_DISK', 'local'),

    'package_directory' => (string) env('UPDATE_RELEASES_PACKAGE_DIRECTORY', 'updates/releases'),

    'max_package_size_mb' => (int) env('UPDATE_RELEASES_MAX_PACKAGE_SIZE_MB', 300),

    'manifest_rate_limit_per_minute' => (int) env('UPDATE_RELEASES_MANIFEST_RATE_LIMIT', 120),

    'download_rate_limit_per_minute' => (int) env('UPDATE_RELEASES_DOWNLOAD_RATE_LIMIT', 120),
];
