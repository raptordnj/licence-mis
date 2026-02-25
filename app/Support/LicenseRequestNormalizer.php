<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

readonly class LicenseRequestNormalizer
{
    public function normalizeDomain(string $domain): string
    {
        $value = trim(mb_strtolower($domain));

        if ($value === '') {
            throw new InvalidArgumentException('Domain is required.');
        }

        if (! str_contains($value, '://')) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            throw new InvalidArgumentException('Invalid domain.');
        }

        $host = trim(mb_strtolower($host));

        if ((bool) config('license_manager.normalize_www', true)) {
            $host = preg_replace('/^www\./', '', $host) ?? $host;
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if ($ascii !== false) {
                $host = $ascii;
            }
        }

        return $host;
    }

    public function normalizeAppUrl(string $appUrl): string
    {
        $value = trim($appUrl);

        if ($value === '') {
            throw new InvalidArgumentException('App URL is required.');
        }

        if (! str_contains($value, '://')) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);

        if (! is_array($parts)) {
            throw new InvalidArgumentException('Invalid app URL.');
        }

        $scheme = mb_strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = (string) ($parts['host'] ?? '');

        if ($host === '') {
            throw new InvalidArgumentException('Invalid app URL.');
        }

        $host = $this->normalizeDomain($host);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) && is_string($parts['path']) ? $parts['path'] : '';
        $path = '/'.ltrim($path, '/');

        if ($path === '/') {
            return "{$scheme}://{$host}{$port}";
        }

        return rtrim("{$scheme}://{$host}{$port}{$path}", '/');
    }
}
