<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Data\Responses\AdminSettingsResponseData;
use App\Services\Contracts\SensitiveSettingsStoreInterface;

readonly class AdminSettingsViewModel
{
    public function __construct(private SensitiveSettingsStoreInterface $settingsStore)
    {
    }

    public function toData(): AdminSettingsResponseData
    {
        return new AdminSettingsResponseData(
            has_envato_api_token: $this->settingsStore->hasEnvatoToken(),
            has_license_hmac_key: $this->settingsStore->hasHmacKey(),
            envato_api_base_url: (string) config('services.envato.base_url'),
            envato_mock_mode: $this->settingsStore->isEnvatoMockModeEnabled(),
        );
    }
}
