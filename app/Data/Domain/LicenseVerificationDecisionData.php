<?php

declare(strict_types=1);

namespace App\Data\Domain;

use App\Enums\LicenseValidationReason;
use App\Models\LicenseInstance;
use Spatie\LaravelData\Data;

class LicenseVerificationDecisionData extends Data
{
    public function __construct(
        public string $status,
        public LicenseValidationReason $reason,
        public int $productId,
        public string $instanceId,
        public string $domain,
        public ?LicenseInstance $instance,
    ) {
    }
}
