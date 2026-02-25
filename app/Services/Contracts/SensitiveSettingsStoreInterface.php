<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface SensitiveSettingsStoreInterface
{
    public function saveEnvatoToken(string $token): void;

    public function saveHmacKey(string $key): void;

    public function saveEnvatoMockMode(bool $enabled): void;

    public function getEnvatoToken(): ?string;

    public function getHmacKey(): ?string;

    public function getEnvatoMockMode(): ?bool;

    public function hasEnvatoToken(): bool;

    public function hasHmacKey(): bool;

    public function isEnvatoMockModeEnabled(): bool;
}
