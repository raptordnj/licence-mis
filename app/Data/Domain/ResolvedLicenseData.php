<?php

declare(strict_types=1);

namespace App\Data\Domain;

use App\Enums\LicenseValidationReason;
use App\Models\License;
use App\Models\Product;

final readonly class ResolvedLicenseData
{
    public function __construct(
        public string $purchaseCode,
        public ?Product $product,
        public ?License $license,
        public ?ValidationResultDTO $validationResult,
        public ?LicenseValidationReason $failureReason,
    ) {
    }

    public function hasFailure(): bool
    {
        return $this->failureReason !== null;
    }
}
