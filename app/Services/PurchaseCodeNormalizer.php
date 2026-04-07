<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

readonly class PurchaseCodeNormalizer
{
    private const MAX_PURCHASE_CODE_LENGTH = 255;

    private const SHA512_HEX_LENGTH = 128;

    public function normalize(string $purchaseCode): string
    {
        $normalized = trim($purchaseCode);

        if ($normalized === '') {
            throw new InvalidArgumentException('Purchase code is required.');
        }

        $normalized = preg_replace('/\s+/u', '', $normalized) ?? $normalized;

        if ($normalized === '') {
            throw new InvalidArgumentException('Purchase code is required.');
        }

        if (mb_strlen($normalized) > self::MAX_PURCHASE_CODE_LENGTH) {
            throw new InvalidArgumentException('Purchase code is too long.');
        }

        return $normalized;
    }

    public function isSha512Hash(string $purchaseCode): bool
    {
        return preg_match('/\A[a-f0-9]{'.self::SHA512_HEX_LENGTH.'}\z/i', $purchaseCode) === 1;
    }

    public function toSha512Hash(string $purchaseCode): string
    {
        $normalized = $this->normalize($purchaseCode);

        if ($this->isSha512Hash($normalized)) {
            return mb_strtolower($normalized);
        }

        return hash('sha512', $normalized);
    }
}
