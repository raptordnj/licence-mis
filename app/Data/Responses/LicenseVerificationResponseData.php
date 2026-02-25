<?php

declare(strict_types=1);

namespace App\Data\Responses;

use App\Enums\LicenseStatus;
use Spatie\LaravelData\Data;

class LicenseVerificationResponseData extends Data
{
    public function __construct(
        public string $purchaseCode,
        public LicenseStatus $status,
        public string $boundDomain,
        public int $envatoItemId,
        public ?string $supportedUntil,
        public ?string $signature,
    ) {
    }
}
