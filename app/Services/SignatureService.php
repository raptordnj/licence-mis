<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\SensitiveSettingsStoreInterface;
use App\ValueObjects\Signature;
use InvalidArgumentException;

readonly class SignatureService
{
    public function __construct(private SensitiveSettingsStoreInterface $sensitiveSettingsStore)
    {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sign(array $payload): Signature
    {
        $configuredKey = (string) config('services.license.hmac_key');
        $storedKey = $this->sensitiveSettingsStore->getHmacKey();
        $key = $storedKey ?: $configuredKey;

        if ($key === '') {
            throw new InvalidArgumentException('Missing HMAC signing key.');
        }

        $canonicalPayload = $this->canonicalize($payload);
        $json = json_encode($canonicalPayload, JSON_THROW_ON_ERROR);

        return new Signature(hash_hmac('sha256', $json, $key));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verify(array $payload, string $signature): bool
    {
        $expected = (string) $this->sign($payload);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function canonicalize(array $payload): array
    {
        ksort($payload);

        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $payload[$key] = $this->canonicalize($value);
            }
        }

        return $payload;
    }
}
