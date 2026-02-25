<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Domain\EnvatoVerificationData;
use App\Enums\Marketplace;
use App\Models\License;

interface LicenseRepositoryInterface
{
    public function findByPurchaseCode(string $purchaseCode): ?License;

    public function createFromVerification(
        string $purchaseCode,
        string $boundDomain,
        Marketplace $marketplace,
        EnvatoVerificationData $verification,
    ): License;

    public function save(License $license): License;
}
