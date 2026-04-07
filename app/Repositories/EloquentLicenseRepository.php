<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Domain\EnvatoVerificationData;
use App\Enums\LicenseStatus;
use App\Enums\Marketplace;
use App\Models\License;
use Illuminate\Database\Eloquent\Builder;

class EloquentLicenseRepository implements LicenseRepositoryInterface
{
    public function findByPurchaseCode(string $purchaseCode): ?License
    {
        return $this->findByPurchaseCodeUsingQuery(License::query(), $purchaseCode);
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

    private function findByPurchaseCodeUsingQuery(Builder $query, string $purchaseCode): ?License
    {
        $normalized = mb_strtolower(trim($purchaseCode));

        if ($normalized === '') {
            return null;
        }

        $exactMatch = (clone $query)
            ->whereRaw('LOWER(purchase_code) = ?', [$normalized])
            ->first();

        if ($exactMatch instanceof License) {
            return $exactMatch;
        }

        if (! $this->looksLikeSha512Hash($normalized)) {
            return null;
        }

        $matchedLicenseId = null;

        (clone $query)
            ->select(['id', 'purchase_code'])
            ->orderBy('id')
            ->chunkById(200, function ($licenses) use (&$matchedLicenseId, $normalized): bool {
                foreach ($licenses as $license) {
                    $storedCode = trim((string) $license->purchase_code);
                    if ($storedCode === '') {
                        continue;
                    }

                    $storedCodeHash = $this->looksLikeSha512Hash($storedCode)
                        ? mb_strtolower($storedCode)
                        : hash('sha512', $storedCode);

                    if (hash_equals($normalized, $storedCodeHash)) {
                        $matchedLicenseId = $license->id;

                        return false;
                    }
                }

                return true;
            }, 'id');

        if (! is_int($matchedLicenseId)) {
            return null;
        }

        return License::query()->find($matchedLicenseId);
    }

    private function looksLikeSha512Hash(string $value): bool
    {
        return preg_match('/\A[a-f0-9]{128}\z/i', $value) === 1;
    }
}
