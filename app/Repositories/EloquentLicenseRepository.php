<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Domain\EnvatoVerificationData;
use App\Enums\LicenseStatus;
use App\Enums\Marketplace;
use App\Models\License;

class EloquentLicenseRepository implements LicenseRepositoryInterface
{
    public function findByPurchaseCode(string $purchaseCode): ?License
    {
        return License::query()
            ->where('purchase_code', $purchaseCode)
            ->first();
    }

    public function createFromVerification(
        string $purchaseCode,
        string $boundDomain,
        Marketplace $marketplace,
        EnvatoVerificationData $verification,
    ): License {
        return License::query()->create([
            'purchase_code' => $purchaseCode,
            'marketplace' => $marketplace,
            'envato_item_id' => $verification->itemId,
            'status' => LicenseStatus::ACTIVE,
            'bound_domain' => $boundDomain,
            'supported_until' => $verification->supportedUntil,
            'verified_at' => now(),
            'metadata' => [
                'item_name' => $verification->itemName,
                'license_type' => 'regular',
                'version' => null,
            ],
        ]);
    }

    public function save(License $license): License
    {
        $license->save();

        return $license->refresh();
    }
}
