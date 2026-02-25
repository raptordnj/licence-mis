<?php

declare(strict_types=1);

namespace App\Data\Requests;

use Spatie\LaravelData\Data;

class PublicLicenseVerifyRequestData extends Data
{
    public function __construct(
        public string $purchaseCode,
        public ?int $productId,
        public ?int $envatoItemId,
        public string $instanceId,
        public string $domain,
        public string $appUrl,
        public ?string $appVersion,
        public ?string $signatureProof,
    ) {
    }
}
