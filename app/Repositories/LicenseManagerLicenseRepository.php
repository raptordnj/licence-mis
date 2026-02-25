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

readonly class LicenseManagerLicenseRepository
{
    public function findByPurchaseCodeAndProduct(string $purchaseCode, int $productId): ?License
    {
        return License::query()
            ->where('purchase_code', $purchaseCode)
            ->where('product_id', $productId)
            ->first();
    }

    public function findByPurchaseCode(string $purchaseCode): ?License
    {
        return License::query()
            ->where('purchase_code', $purchaseCode)
            ->first();
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
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($license->metadata) ? $license->metadata : [];

        $license->forceFill([
            'product_id' => $product->id,
            'buyer' => $license->buyer ?? $validationResult->buyer,
            'marketplace' => $license->marketplace ?? Marketplace::ENVATO,
            'envato_item_id' => $license->envato_item_id ?? $validationResult->envatoItemId ?? $product->envato_item_id,
            'supported_until' => $license->supported_until ?? $validationResult->supportedUntil,
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
}
