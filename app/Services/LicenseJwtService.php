<?php

declare(strict_types=1);

namespace App\Services;

use OpenSSLAsymmetricKey;
use RuntimeException;

readonly class LicenseJwtService
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function issue(array $claims): string
    {
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
            'kid' => (string) config('license_manager.jwt.key_id', 'license-v1'),
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR)),
        ];

        $signingInput = implode('.', $segments);
        $privateKey = $this->privateKey();

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if ($signed !== true) {
            throw new RuntimeException('Unable to sign JWT payload.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function privateKey(): OpenSSLAsymmetricKey
    {
        $path = (string) config('license_manager.jwt.private_key_path');
        $keyData = @file_get_contents($path);

        if (! is_string($keyData) || trim($keyData) === '') {
            throw new RuntimeException("JWT private key is missing at [{$path}].");
        }

        $privateKey = openssl_pkey_get_private($keyData);

        if ($privateKey === false) {
            throw new RuntimeException("JWT private key at [{$path}] is invalid.");
        }

        return $privateKey;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
