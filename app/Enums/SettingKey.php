<?php

declare(strict_types=1);

namespace App\Enums;

enum SettingKey: string
{
    case ENVATO_API_TOKEN = 'envato_api_token';
    case LICENSE_HMAC_KEY = 'license_hmac_key';
    case ENVATO_MOCK_MODE = 'envato_mock_mode';
}
