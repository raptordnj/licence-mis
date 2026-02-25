<?php

declare(strict_types=1);

namespace App\Data\Requests;

use Spatie\LaravelData\Data;

class PublicLicenseDeactivateRequestData extends Data
{
    public function __construct(
        public string $purchaseCode,
        public ?int $productId,
        public ?int $envatoItemId,
        public string $instanceId,
    ) {
    }
}
