<?php

declare(strict_types=1);

namespace App\Actions\License;

use App\Data\Domain\LicenseVerificationInputData;
use App\Data\Responses\LicenseVerificationResponseData;
use App\Enums\LicenseStatus;
use App\Enums\Marketplace;
use App\Exceptions\DomainMismatchException;
use App\Exceptions\LicenseRevokedException;
use App\Exceptions\PurchaseInvalidException;
use App\Models\License;
use App\Repositories\LicenseRepositoryInterface;
use App\Services\Contracts\EnvatoVerifierInterface;
use App\Services\SignatureService;
use App\Support\DomainNormalizer;
use Illuminate\Support\Carbon;

readonly class VerifyLicenseAction
{
    public function __construct(
        private LicenseRepositoryInterface $licenseRepository,
        private EnvatoVerifierInterface $envatoVerifier,
        private DomainNormalizer $domainNormalizer,
        private SignatureService $signatureService,
    ) {
    }

    public function execute(LicenseVerificationInputData $input): LicenseVerificationResponseData
    {
        $normalizedDomain = (string) $this->domainNormalizer->normalize($input->domain);

        $license = $this->licenseRepository->findByPurchaseCode($input->purchaseCode);

        if ($license instanceof License) {
            return $this->verifyExistingLicense($license, $normalizedDomain, $input->itemId);
        }

        $verification = $this->envatoVerifier->verifyPurchaseCode(
            $input->purchaseCode,
            $input->itemId,
            $input->productId,
        );

        if (! $verification->valid) {
            throw new PurchaseInvalidException();
        }

        if ($input->itemId !== null && $verification->itemId !== $input->itemId) {
            throw new PurchaseInvalidException('Purchase is not valid for this item.');
        }

        $license = $this->licenseRepository->createFromVerification(
            purchaseCode: $input->purchaseCode,
            boundDomain: $normalizedDomain,
            marketplace: Marketplace::ENVATO,
            verification: $verification,
        );

        return $this->toResponseData($license);
    }

    private function verifyExistingLicense(License $license, string $normalizedDomain, ?int $itemId): LicenseVerificationResponseData
    {
        $status = $this->resolveStatus($license);

        if ($status === LicenseStatus::REVOKED) {
            throw new LicenseRevokedException();
        }

        if ($license->bound_domain !== null && $license->bound_domain !== $normalizedDomain) {
            throw new DomainMismatchException($license->bound_domain, $normalizedDomain);
        }

        if ($itemId !== null && $license->envato_item_id !== $itemId) {
            throw new PurchaseInvalidException('Purchase is not valid for this item.');
        }

        $updates = [
            'verified_at' => now(),
        ];

        if ($license->bound_domain === null) {
            $updates['bound_domain'] = $normalizedDomain;
        }

        $license->forceFill($updates);
        $saved = $this->licenseRepository->save($license);

        return $this->toResponseData($saved);
    }

    private function toResponseData(License $license): LicenseVerificationResponseData
    {
        $status = $this->resolveStatus($license);
        $boundDomain = (string) ($license->bound_domain ?? '');

        $signaturePayload = [
            'purchase_code' => $license->purchase_code,
            'status' => $status->value,
            'bound_domain' => $boundDomain,
            'envato_item_id' => $license->envato_item_id,
        ];

        return new LicenseVerificationResponseData(
            purchaseCode: $license->purchase_code,
            status: $status,
            boundDomain: $boundDomain,
            envatoItemId: (int) $license->envato_item_id,
            supportedUntil: $this->supportedUntilToIsoString($license),
            signature: (string) $this->signatureService->sign($signaturePayload),
        );
    }

    private function resolveStatus(License $license): LicenseStatus
    {
        $status = $license->status;

        if ($status instanceof LicenseStatus) {
            return $status;
        }

        return LicenseStatus::from((string) $status);
    }

    private function supportedUntilToIsoString(License $license): ?string
    {
        $supportedUntil = $license->supported_until;

        if ($supportedUntil instanceof Carbon) {
            return $supportedUntil->toIso8601String();
        }

        if (is_string($supportedUntil) && $supportedUntil !== '') {
            return Carbon::parse($supportedUntil)->toIso8601String();
        }

        return null;
    }
}
