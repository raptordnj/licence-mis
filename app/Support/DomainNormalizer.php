<?php

declare(strict_types=1);

namespace App\Support;

use App\ValueObjects\Domain;
use InvalidArgumentException;

readonly class DomainNormalizer
{
    public function normalize(string $domain): Domain
    {
        $value = trim(strtolower($domain));

        if ($value === '') {
            throw new InvalidArgumentException('Domain is required.');
        }

        if (! str_starts_with($value, 'http://') && ! str_starts_with($value, 'https://')) {
            $value = "https://{$value}";
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            throw new InvalidArgumentException('Invalid domain.');
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                $host = $ascii;
            }
        }

        return new Domain($host);
    }
}
