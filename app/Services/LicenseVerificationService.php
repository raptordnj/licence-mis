<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Domain\ResolvedLicenseData;
use App\Data\Domain\ValidationResultDTO;
use App\Enums\LicenseValidationReason;
use App\Models\License;
use App\Models\Product;
use App\Repositories\LicenseManagerLicenseRepository;
use App\Repositories\LicenseManagerProductRepository;
use App\Services\Contracts\EnvatoPurchaseValidatorInterface;

readonly class LicenseVerificationService
{
    public function __construct(
        private LicenseManagerLicenseRepository $licenseRepository,
        private LicenseManagerProductRepository $productRepository,
        private EnvatoPurchaseValidatorInterface $purchaseValidator,
        private PurchaseCodeNormalizer $purchaseCodeNormalizer,
    ) {
    }

    public function normalizePurchaseCode(string $purchaseCode): string
    {
        return $this->purchaseCodeNormalizer->normalize($purchaseCode);
    }

    public function purchaseCodesMatch(string $first, string $second): bool
    {
        $firstHash = $this->purchaseCodeNormalizer->toSha512Hash($first);
        $secondHash = $this->purchaseCodeNormalizer->toSha512Hash($second);

        return hash_equals($firstHash, $secondHash);
    }

    public function resolveForProduct(string $purchaseCode, Product $product): ResolvedLicenseData
    {
        $normalizedPurchaseCode = $this->normalizePurchaseCode($purchaseCode);
        $license = $this->licenseRepository->findByPurchaseCodeAndProduct($normalizedPurchaseCode, $product->id);

        if (! $license instanceof License) {
            $licenseByPurchaseCode = $this->licenseRepository->findByPurchaseCode($normalizedPurchaseCode);

            if ($licenseByPurchaseCode instanceof License) {
                if ($this->belongsToAnotherProduct($licenseByPurchaseCode, $product)) {
                    return $this->failed($normalizedPurchaseCode, LicenseValidationReason::NOT_FOUND, null, $product);
                }

                if ($this->belongsToAnotherItem($licenseByPurchaseCode, $product)) {
                    return $this->failed($normalizedPurchaseCode, LicenseValidationReason::NOT_FOUND, null, $product);
                }

                $license = $this->licenseRepository->attachProduct($licenseByPurchaseCode, $product);
            }
        }

        if ($license instanceof License) {
            return new ResolvedLicenseData(
                purchaseCode: $normalizedPurchaseCode,
                product: $product,
                license: $license,
                validationResult: null,
                failureReason: null,
            );
        }

        if ($this->purchaseCodeNormalizer->isSha512Hash($normalizedPurchaseCode)) {
            return $this->failed(
                $normalizedPurchaseCode,
                LicenseValidationReason::NOT_FOUND,
                null,
                $product,
            );
        }

        return $this->resolveViaProviderForProduct($normalizedPurchaseCode, $product);
    }

    public function resolveForUnknownProduct(string $purchaseCode, ?Product $preferredProduct = null): ResolvedLicenseData
    {
        $normalizedPurchaseCode = $this->normalizePurchaseCode($purchaseCode);
        $localLicense = $this->licenseRepository->findByPurchaseCode($normalizedPurchaseCode);

        if ($localLicense instanceof License) {
            $product = $this->resolveProductByLocalLicense($localLicense);

            if (! $product instanceof Product && $preferredProduct instanceof Product && $preferredProduct->isActive()) {
                $product = $preferredProduct;
            }

            if (! $product instanceof Product || ! $product->isActive()) {
                return $this->failed(
                    $normalizedPurchaseCode,
                    LicenseValidationReason::NOT_FOUND,
                    null,
                    $product,
                );
            }

            return new ResolvedLicenseData(
                purchaseCode: $normalizedPurchaseCode,
                product: $product,
                license: $localLicense,
                validationResult: null,
                failureReason: null,
            );
        }

        if ($this->purchaseCodeNormalizer->isSha512Hash($normalizedPurchaseCode)) {
            return $this->failed(
                $normalizedPurchaseCode,
                LicenseValidationReason::NOT_FOUND,
                null,
                $preferredProduct,
            );
        }

        $validationResult = $this->purchaseValidator->validate(
            purchaseCode: $normalizedPurchaseCode,
            envatoItemId: null,
            productId: null,
        );

        if (! $validationResult->valid) {
            return $this->failed(
                $normalizedPurchaseCode,
                $validationResult->reason,
                $validationResult,
            );
        }

        $product = $this->resolveProductByItemId($validationResult->envatoItemId);

        if (! $product instanceof Product && $preferredProduct instanceof Product && $preferredProduct->isActive()) {
            $product = $preferredProduct;
        }

        if (! $product instanceof Product || ! $product->isActive()) {
            return $this->failed(
                $normalizedPurchaseCode,
                LicenseValidationReason::NOT_FOUND,
                $validationResult,
                $product,
            );
        }

        return $this->persistValidatedPurchase(
            purchaseCode: $normalizedPurchaseCode,
            product: $product,
            validationResult: $validationResult,
        );
    }

    private function resolveViaProviderForProduct(string $purchaseCode, Product $product): ResolvedLicenseData
    {
        $validationResult = $this->purchaseValidator->validate(
            purchaseCode: $purchaseCode,
            envatoItemId: $product->envato_item_id,
            productId: $product->id,
        );

        if (! $validationResult->valid) {
            if ($this->canRetryWithoutItemScope($validationResult)) {
                return $this->resolveForUnknownProduct($purchaseCode, $product);
            }

            return $this->failed(
                $purchaseCode,
                $validationResult->reason,
                $validationResult,
                $product,
            );
        }

        return $this->persistValidatedPurchase(
            purchaseCode: $purchaseCode,
            product: $product,
            validationResult: $validationResult,
        );
    }

    private function persistValidatedPurchase(
        string $purchaseCode,
        Product $product,
        ValidationResultDTO $validationResult,
    ): ResolvedLicenseData {
        $licenseByPurchaseCode = $this->licenseRepository->findByPurchaseCode($purchaseCode);

        if ($licenseByPurchaseCode instanceof License) {
            if ($this->belongsToAnotherProduct($licenseByPurchaseCode, $product)) {
                return $this->failed(
                    $purchaseCode,
                    LicenseValidationReason::NOT_FOUND,
                    $validationResult,
                    $product,
                );
            }

            if ($this->belongsToAnotherItem($licenseByPurchaseCode, $product)) {
                return $this->failed(
                    $purchaseCode,
                    LicenseValidationReason::NOT_FOUND,
                    $validationResult,
                    $product,
                );
            }

            $license = $this->licenseRepository->attachProductAndValidation(
                license: $licenseByPurchaseCode,
                product: $product,
                validationResult: $validationResult,
            );

            return new ResolvedLicenseData(
                purchaseCode: $purchaseCode,
                product: $product,
                license: $license,
                validationResult: $validationResult,
                failureReason: null,
            );
        }

        $license = $this->licenseRepository->createFromValidation(
            purchaseCode: $purchaseCode,
            product: $product,
            validationResult: $validationResult,
        );

        return new ResolvedLicenseData(
            purchaseCode: $purchaseCode,
            product: $product,
            license: $license,
            validationResult: $validationResult,
            failureReason: null,
        );
    }

    private function failed(
        string $purchaseCode,
        LicenseValidationReason $reason,
        ?ValidationResultDTO $validationResult,
        ?Product $product = null,
    ): ResolvedLicenseData {
        return new ResolvedLicenseData(
            purchaseCode: $purchaseCode,
            product: $product,
            license: null,
            validationResult: $validationResult,
            failureReason: $reason,
        );
    }

    private function canRetryWithoutItemScope(ValidationResultDTO $validationResult): bool
    {
        return $validationResult->reason === LicenseValidationReason::NOT_FOUND
            && in_array(
                $validationResult->matchedBy,
                ['envato_api_item_mismatch', 'external_api_item_mismatch'],
                true,
            );
    }

    private function resolveProductByItemId(?int $envatoItemId): ?Product
    {
        if (! is_int($envatoItemId) || $envatoItemId <= 0) {
            return null;
        }

        return $this->productRepository->findByIdentifier(null, $envatoItemId);
    }

    private function resolveProductByLocalLicense(License $license): ?Product
    {
        if (is_int($license->product_id) && $license->product_id > 0) {
            $product = $this->productRepository->findById($license->product_id);

            if ($product instanceof Product) {
                return $product;
            }
        }

        if (is_int($license->envato_item_id) && $license->envato_item_id > 0) {
            return $this->productRepository->findByIdentifier(null, $license->envato_item_id);
        }

        return null;
    }

    private function belongsToAnotherProduct(License $license, Product $product): bool
    {
        return $license->product_id !== null && $license->product_id !== $product->id;
    }

    private function belongsToAnotherItem(License $license, Product $product): bool
    {
        return $license->envato_item_id !== null
            && $license->envato_item_id !== $product->envato_item_id;
    }
}
