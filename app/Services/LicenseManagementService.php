<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Domain\LicenseDeactivationDecisionData;
use App\Data\Domain\LicenseVerificationDecisionData;
use App\Data\Domain\ValidationResultDTO;
use App\Data\Requests\PublicLicenseDeactivateRequestData;
use App\Data\Requests\PublicLicenseVerifyRequestData;
use App\Enums\LicenseInstanceStatus;
use App\Enums\LicenseStatus;
use App\Enums\LicenseValidationReason;
use App\Models\License;
use App\Models\LicenseInstance;
use App\Models\Product;
use App\Repositories\LicenseManagerLicenseRepository;
use App\Repositories\LicenseManagerProductRepository;
use App\Services\Contracts\EnvatoPurchaseValidatorInterface;
use App\Support\LicenseRequestNormalizer;
use InvalidArgumentException;

readonly class LicenseManagementService
{
    public function __construct(
        private LicenseManagerProductRepository $productRepository,
        private LicenseManagerLicenseRepository $licenseRepository,
        private LicenseRequestNormalizer $requestNormalizer,
        private EnvatoPurchaseValidatorInterface $purchaseValidator,
    ) {
    }

    public function verify(
        PublicLicenseVerifyRequestData $request,
        string $ipAddress,
        ?string $userAgent,
    ): LicenseVerificationDecisionData {
        $domain = $this->requestNormalizer->normalizeDomain($request->domain);
        $appUrl = $this->requestNormalizer->normalizeAppUrl($request->appUrl);

        $product = $this->productRepository->findByIdentifier($request->productId, $request->envatoItemId);

        if ($product === null || ! $product->isActive()) {
            return new LicenseVerificationDecisionData(
                status: 'invalid',
                reason: LicenseValidationReason::NOT_FOUND,
                productId: $request->productId ?? 0,
                instanceId: $request->instanceId,
                domain: $domain,
                instance: null,
            );
        }

        $validationResult = null;
        $license = null;
        $domainLicense = $this->licenseRepository->findByActiveDomainAndProduct($domain, $product->id);

        if ($domainLicense instanceof License) {
            if ($domainLicense->purchase_code !== $request->purchaseCode) {
                return new LicenseVerificationDecisionData(
                    status: 'invalid',
                    reason: LicenseValidationReason::DOMAIN_MISMATCH,
                    productId: $product->id,
                    instanceId: $request->instanceId,
                    domain: $domain,
                    instance: null,
                );
            }

            $license = $domainLicense;
        } else {
            $license = $this->licenseRepository->findByPurchaseCodeAndProduct($request->purchaseCode, $product->id);
        }

        if (! $license instanceof License) {
            $validationResult = $this->purchaseValidator->validate(
                purchaseCode: $request->purchaseCode,
                envatoItemId: $product->envato_item_id,
                productId: $product->id,
            );

            if (! $validationResult->valid) {
                return new LicenseVerificationDecisionData(
                    status: 'invalid',
                    reason: $validationResult->reason,
                    productId: $product->id,
                    instanceId: $request->instanceId,
                    domain: $domain,
                    instance: null,
                );
            }

            $licenseByPurchaseCode = $this->licenseRepository->findByPurchaseCode($request->purchaseCode);

            if (
                $licenseByPurchaseCode instanceof License
                && $licenseByPurchaseCode->product_id !== null
                && $licenseByPurchaseCode->product_id !== $product->id
            ) {
                return new LicenseVerificationDecisionData(
                    status: 'invalid',
                    reason: LicenseValidationReason::NOT_FOUND,
                    productId: $product->id,
                    instanceId: $request->instanceId,
                    domain: $domain,
                    instance: null,
                );
            }

            if ($licenseByPurchaseCode instanceof License) {
                $license = $this->licenseRepository->attachProductAndValidation(
                    license: $licenseByPurchaseCode,
                    product: $product,
                    validationResult: $validationResult,
                );
            } else {
                $license = $this->licenseRepository->createFromValidation(
                    purchaseCode: $request->purchaseCode,
                    product: $product,
                    validationResult: $validationResult,
                );
            }
        }

        $domainRestrictions = $this->resolveDomainRestrictions($license, $validationResult);

        if ($domainRestrictions !== [] && ! in_array($domain, $domainRestrictions, true)) {
            return new LicenseVerificationDecisionData(
                status: 'invalid',
                reason: LicenseValidationReason::DOMAIN_MISMATCH,
                productId: $product->id,
                instanceId: $request->instanceId,
                domain: $domain,
                instance: null,
            );
        }

        if ($license->status === LicenseStatus::REVOKED) {
            return new LicenseVerificationDecisionData(
                status: 'invalid',
                reason: LicenseValidationReason::REVOKED,
                productId: $product->id,
                instanceId: $request->instanceId,
                domain: $domain,
                instance: null,
            );
        }

        if (
            $license->status === LicenseStatus::REFUNDED
            || $license->status === LicenseStatus::CHARGEBACK
        ) {
            return new LicenseVerificationDecisionData(
                status: 'invalid',
                reason: LicenseValidationReason::REFUND,
                productId: $product->id,
                instanceId: $request->instanceId,
                domain: $domain,
                instance: null,
            );
        }

        $instance = $this->licenseRepository->findInstance($license, $request->instanceId);
        $activeInstancesCount = $this->licenseRepository->activeInstancesCount($license);
        $boundDomain = $this->licenseRepository->firstActiveDomain($license);

        if (
            $product->strict_domain_binding
            && $boundDomain !== null
            && $boundDomain !== $domain
            && (
                ! $instance instanceof LicenseInstance
                || $instance->status !== LicenseInstanceStatus::ACTIVE
            )
        ) {
            return new LicenseVerificationDecisionData(
                status: 'invalid',
                reason: LicenseValidationReason::DOMAIN_MISMATCH,
                productId: $product->id,
                instanceId: $request->instanceId,
                domain: $domain,
                instance: $instance,
            );
        }

        if ($instance instanceof LicenseInstance && $instance->status === LicenseInstanceStatus::ACTIVE) {
            $instance->forceFill([
                'domain' => $domain,
                'app_url' => $appUrl,
                'ip' => $ipAddress,
                'user_agent' => $userAgent,
                'last_seen_at' => now(),
            ])->save();

            $this->syncLicenseBoundDomain($license);

            return new LicenseVerificationDecisionData(
                status: 'valid',
                reason: LicenseValidationReason::NONE,
                productId: $product->id,
                instanceId: $instance->instance_id,
                domain: $domain,
                instance: $instance->refresh(),
            );
        }

        $activationLimit = $this->resolveActivationLimit(
            product: $product,
            license: $license,
            validationResult: $validationResult,
        );

        if ($activeInstancesCount >= $activationLimit) {
            return new LicenseVerificationDecisionData(
                status: 'invalid',
                reason: LicenseValidationReason::LIMIT_REACHED,
                productId: $product->id,
                instanceId: $request->instanceId,
                domain: $domain,
                instance: $instance,
            );
        }

        if ($instance instanceof LicenseInstance) {
            $instance->forceFill([
                'domain' => $domain,
                'app_url' => $appUrl,
                'ip' => $ipAddress,
                'user_agent' => $userAgent,
                'last_seen_at' => now(),
                'activated_at' => now(),
                'deactivated_at' => null,
                'status' => LicenseInstanceStatus::ACTIVE,
            ])->save();
        } else {
            $instance = $license->instances()->create([
                'instance_id' => $request->instanceId,
                'domain' => $domain,
                'app_url' => $appUrl,
                'ip' => $ipAddress,
                'user_agent' => $userAgent,
                'last_seen_at' => now(),
                'activated_at' => now(),
                'status' => LicenseInstanceStatus::ACTIVE,
            ]);
        }

        $this->syncLicenseBoundDomain($license);

        return new LicenseVerificationDecisionData(
            status: 'valid',
            reason: LicenseValidationReason::NONE,
            productId: $product->id,
            instanceId: $instance->instance_id,
            domain: $domain,
            instance: $instance->refresh(),
        );
    }

    public function deactivate(PublicLicenseDeactivateRequestData $request): LicenseDeactivationDecisionData
    {
        $product = $this->productRepository->findByIdentifier($request->productId, $request->envatoItemId);

        if ($product === null) {
            return new LicenseDeactivationDecisionData(
                success: false,
                reason: LicenseValidationReason::NOT_FOUND,
                productId: $request->productId ?? 0,
                instanceId: $request->instanceId,
                instance: null,
            );
        }

        $license = $this->licenseRepository->findByPurchaseCodeAndProduct($request->purchaseCode, $product->id);

        if ($license === null) {
            return new LicenseDeactivationDecisionData(
                success: false,
                reason: LicenseValidationReason::NOT_FOUND,
                productId: $product->id,
                instanceId: $request->instanceId,
                instance: null,
            );
        }

        $instance = $this->licenseRepository->findInstance($license, $request->instanceId);

        if (! $instance instanceof LicenseInstance) {
            return new LicenseDeactivationDecisionData(
                success: false,
                reason: LicenseValidationReason::NOT_FOUND,
                productId: $product->id,
                instanceId: $request->instanceId,
                instance: null,
            );
        }

        if ($instance->status === LicenseInstanceStatus::ACTIVE) {
            $instance->forceFill([
                'status' => LicenseInstanceStatus::INACTIVE,
                'deactivated_at' => now(),
                'last_seen_at' => now(),
            ])->save();
        }

        $this->syncLicenseBoundDomain($license);

        return new LicenseDeactivationDecisionData(
            success: true,
            reason: LicenseValidationReason::DEACTIVATED,
            productId: $product->id,
            instanceId: $instance->instance_id,
            instance: $instance->refresh(),
        );
    }

    /**
     * @return array<int, string>
     */
    private function resolveDomainRestrictions(License $license, ?ValidationResultDTO $validationResult): array
    {
        $domainRestrictions = $validationResult instanceof ValidationResultDTO
            ? $validationResult->domainRestrictions
            : [];

        if ($domainRestrictions === []) {
            $metadataRestrictions = data_get($license->metadata, 'mock.domain_restrictions');
            $domainRestrictions = is_array($metadataRestrictions) ? $metadataRestrictions : [];
        }

        $normalized = [];

        foreach ($domainRestrictions as $domainValue) {
            if (! is_string($domainValue)) {
                continue;
            }

            try {
                $normalizedDomain = $this->requestNormalizer->normalizeDomain($domainValue);
            } catch (InvalidArgumentException) {
                continue;
            }

            if ($normalizedDomain !== '') {
                $normalized[] = $normalizedDomain;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function resolveActivationLimit(
        Product $product,
        License $license,
        ?ValidationResultDTO $validationResult,
    ): int {
        $activationLimit = max(1, $product->activation_limit);
        $maxActivations = $validationResult?->maxActivations;

        if (! is_int($maxActivations)) {
            $metadataMaxActivations = data_get($license->metadata, 'mock.max_activations');
            $maxActivations = is_numeric($metadataMaxActivations) ? (int) $metadataMaxActivations : null;
        }

        if (is_int($maxActivations) && $maxActivations > 0) {
            $activationLimit = min($activationLimit, $maxActivations);
        }

        return max(1, $activationLimit);
    }

    private function syncLicenseBoundDomain(License $license): void
    {
        $activeDomain = $this->licenseRepository->firstActiveDomain($license);

        if ($license->bound_domain === $activeDomain) {
            return;
        }

        $license->forceFill([
            'bound_domain' => $activeDomain,
        ])->save();
    }
}
