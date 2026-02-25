<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Setting;
use App\Services\Contracts\SensitiveSettingsStoreInterface;

readonly class SensitiveSettingService implements SensitiveSettingsStoreInterface
{
    public function saveEnvatoToken(string $token): void
    {
        Setting::query()->updateOrCreate(
            ['key' => SettingKey::ENVATO_API_TOKEN->value],
            ['value' => $token],
        );
    }

    public function saveHmacKey(string $key): void
    {
        Setting::query()->updateOrCreate(
            ['key' => SettingKey::LICENSE_HMAC_KEY->value],
            ['value' => $key],
        );
    }

    public function saveEnvatoMockMode(bool $enabled): void
    {
        Setting::query()->updateOrCreate(
            ['key' => SettingKey::ENVATO_MOCK_MODE->value],
            ['value' => $enabled ? '1' : '0'],
        );
    }

    public function getEnvatoToken(): ?string
    {
        return Setting::query()->where('key', SettingKey::ENVATO_API_TOKEN->value)->value('value');
    }

    public function getHmacKey(): ?string
    {
        return Setting::query()->where('key', SettingKey::LICENSE_HMAC_KEY->value)->value('value');
    }

    public function getEnvatoMockMode(): ?bool
    {
        $value = Setting::query()->where('key', SettingKey::ENVATO_MOCK_MODE->value)->value('value');

        if (! is_string($value)) {
            return null;
        }

        $normalized = mb_strtolower(trim($value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off', '' => false,
            default => null,
        };
    }

    public function hasEnvatoToken(): bool
    {
        $token = $this->getEnvatoToken();

        return is_string($token) && $token !== '';
    }

    public function hasHmacKey(): bool
    {
        $key = $this->getHmacKey();

        return is_string($key) && $key !== '';
    }

    public function isEnvatoMockModeEnabled(): bool
    {
        $stored = $this->getEnvatoMockMode();

        if (is_bool($stored)) {
            return $stored;
        }

        return (bool) config('license_manager.envato_mock.mode', false);
    }
}
