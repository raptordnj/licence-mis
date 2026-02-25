<?php

declare(strict_types=1);

namespace App\Data\Requests;

use Spatie\LaravelData\Data;

class VerifyLicenseRequestData extends Data
{
    public function __construct(
        public string $purchaseCode,
        public string $domain,
        public ?int $itemId,
    ) {
    }
}
