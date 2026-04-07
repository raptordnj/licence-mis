<?php

declare(strict_types=1);

namespace App\Data\Domain;

use Spatie\LaravelData\Data;

class LicenseVerificationInputData extends Data
{
    public function __construct(
        public string $purchaseCode,
        public string $domain,
        public ?int $itemId,
        public ?int $productId,
    ) {
    }
}
