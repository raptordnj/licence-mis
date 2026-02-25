<?php

declare(strict_types=1);

namespace App\Data\Responses;

use Spatie\LaravelData\Data;

class AdminSettingsResponseData extends Data
{
    public function __construct(
        public bool $has_envato_api_token,
        public bool $has_license_hmac_key,
        public string $envato_api_base_url,
        public bool $envato_mock_mode,
    ) {
    }
}
