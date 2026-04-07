<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\Domain\ValidationResultDTO;
use App\Enums\LicenseInstanceStatus;
use App\Enums\LicenseStatus;
use App\Enums\Marketplace;
use App\Models\License;
use App\Models\LicenseInstance;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

readonly class LicenseManagerLicenseRepository
{
    public function findByPurchaseCodeAndProduct(string $purchaseCode, int $productId): ?License
    {
        $query = License::query()
            ->where('product_id', $productId);

        return $this->findByPurchaseCodeUsingQuery(
            query: $query,
            purchaseCode: $purchaseCode,
        );
    }

    public function findByPurchaseCode(string $purchaseCode): ?License
    {
        return $this->findByPurchaseCodeUsingQuery(License::query(), $purchaseCode);
    }

    public function findByActiveDomainAndProduct(string $domain, int $productId): ?License
    {
        $license = License::query()
            ->where('product_id', $productId)
            ->where('bound_domain', $domain)
            ->first();

        if ($license instanceof License) {
            return $license;
        }

        $instance = LicenseInstance::query()
            ->where('status', LicenseInstanceStatus::ACTIVE->value)
            ->where('domain', $domain)
            ->whereHas('license', static function ($query) use ($productId): void {
                $query->where('product_id', $productId);
            })
            ->orderByDesc('last_seen_at')
            ->orderByDesc('activated_at')
            ->first();

        if (! $instance instanceof LicenseInstance) {
            return null;
        }

        return $instance->license()->first();
    }

    public function createFromValidation(
        string $purchaseCode,
        Product $product,
        ValidationResultDTO $validationResult,
    ): License {
        return License::query()->create([
            'product_id' => $product->id,
            'purchase_code' => $purchaseCode,
            'buyer' => $validationResult->buyer,
            'marketplace' => Marketplace::ENVATO,
            'envato_item_id' => $validationResult->envatoItemId ?? $product->envato_item_id,
            'status' => LicenseStatus::VALID,
            'supported_until' => $validationResult->supportedUntil,
            'verified_at' => now(),
            'metadata' => $this->buildValidationMetadata([], $validationResult),
        ]);
    }

    public function attachProductAndValidation(
        License $license,
        Product $product,
        ValidationResultDTO $validationResult,
    ): License {
        return $this->refreshFromValidation($license, $product, $validationResult);
    }

    public function attachProduct(License $license, Product $product): License
    {
        $license->forceFill([
            'product_id' => $product->id,
            'marketplace' => $license->marketplace ?? Marketplace::ENVATO,
            'envato_item_id' => $license->envato_item_id ?? $product->envato_item_id,
            'verified_at' => $license->verified_at ?? now(),
        ])->save();

        return $license->refresh();
    }

    public function refreshFromValidation(
        License $license,
        Product $product,
        ValidationResultDTO $validationResult,
    ): License {
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($license->metadata) ? $license->metadata : [];

        $license->forceFill([
            'product_id' => $product->id,
            'buyer' => $validationResult->buyer ?? $license->buyer,
            'marketplace' => $license->marketplace ?? Marketplace::ENVATO,
            'envato_item_id' => $validationResult->envatoItemId ?? $license->envato_item_id ?? $product->envato_item_id,
            'status' => LicenseStatus::VALID,
            'supported_until' => $validationResult->supportedUntil ?? $license->supported_until,
            'verified_at' => now(),
            'metadata' => $this->buildValidationMetadata($metadata, $validationResult),
        ])->save();

        return $license->refresh();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function buildValidationMetadata(array $metadata, ValidationResultDTO $validationResult): array
    {
        return [
            ...$metadata,
            'item_name' => $metadata['item_name'] ?? $validationResult->itemName,
            'buyer' => $metadata['buyer'] ?? $validationResult->buyer,
            'buyer_username' => $metadata['buyer_username'] ?? $validationResult->buyer,
            'license_type' => $metadata['license_type'] ?? 'regular',
            'version' => $metadata['version'] ?? null,
            'raw_payload' => $validationResult->rawPayload,
            'mock' => [
                'source' => $validationResult->source,
                'matched_by' => $validationResult->matchedBy,
                'max_activations' => $validationResult->maxActivations,
                'domain_restrictions' => $validationResult->domainRestrictions,
            ],
        ];
    }

    public function findInstance(License $license, string $instanceId): ?LicenseInstance
    {
        return $license->instances()
            ->where('instance_id', $instanceId)
            ->first();
    }

    public function activeInstancesCount(License $license): int
    {
        return $license->instances()
            ->where('status', LicenseInstanceStatus::ACTIVE->value)
            ->count();
    }

    public function firstActiveDomain(License $license): ?string
    {
        return $license->instances()
            ->where('status', LicenseInstanceStatus::ACTIVE->value)
            ->orderBy('activated_at')
            ->value('domain');
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
