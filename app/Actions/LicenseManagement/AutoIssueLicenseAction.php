<?php

declare(strict_types=1);

namespace App\Actions\LicenseManagement;

use App\Data\Requests\PublicLicenseVerifyRequestData;
use App\Data\Responses\PublicLicenseVerifyResponseData;
use App\Enums\LicenseCheckResult;
use App\Enums\LicenseValidationReason;
use App\Services\LicenseCheckLoggerService;
use App\Services\LicenseJwtService;
use App\Services\LicenseManagementService;
use InvalidArgumentException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

readonly class AutoIssueLicenseAction
{
    public function __construct(
        private LicenseManagementService $licenseManagementService,
        private LicenseJwtService $licenseJwtService,
        private LicenseCheckLoggerService $licenseCheckLogger,
    ) {
    }

    /**
     * Auto-issue a license by verifying against Envato API first.
     * If the purchase code is valid in Envato but not in the system,
     * it will be automatically created and issued.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload, string $ipAddress, ?string $userAgent): PublicLicenseVerifyResponseData
    {
        try {
            $validated = $this->validate($payload);

            $requestData = new PublicLicenseVerifyRequestData(
                purchaseCode: (string) data_get($validated, 'purchase_code'),
                productId: data_get($validated, 'product_id') !== null ? (int) data_get($validated, 'product_id') : null,
                envatoItemId: data_get($validated, 'envato_item_id') !== null
                    ? (int) data_get($validated, 'envato_item_id')
                    : null,
                instanceId: (string) data_get($validated, 'instance_id'),
                domain: (string) data_get($validated, 'domain'),
                appUrl: (string) data_get($validated, 'app_url'),
                appVersion: data_get($validated, 'app_version') !== null
                    ? (string) data_get($validated, 'app_version')
                    : null,
                signatureProof: data_get($validated, 'signature_proof') !== null
                    ? (string) data_get($validated, 'signature_proof')
                    : null,
            );
        } catch (ValidationException $exception) {
            $response = $this->buildInvalidBadRequestResponse($payload);

            $this->licenseCheckLogger->log(
                instance: null,
                result: LicenseCheckResult::INVALID,
                reason: LicenseValidationReason::BAD_REQUEST->value,
                requestPayload: $payload,
                responsePayload: $response->toArray(),
            );

            return $response;
        }

        try {
            $decision = $this->licenseManagementService->verify($requestData, $ipAddress, $userAgent);
        } catch (InvalidArgumentException $exception) {
            $response = $this->buildInvalidBadRequestResponse($payload);

            $this->licenseCheckLogger->log(
                instance: null,
                result: LicenseCheckResult::INVALID,
                reason: LicenseValidationReason::BAD_REQUEST->value,
                requestPayload: [
                    ...$payload,
                    'ip' => $ipAddress,
                    'user_agent' => $userAgent,
                ],
                responsePayload: $response->toArray(),
            );

            return $response;
        }

        $issuedAt = (int) now()->timestamp;
        $validUntil = $issuedAt + (
            $decision->status === 'valid'
                ? (int) config('license_manager.token_ttl_seconds', 3600)
                : (int) config('license_manager.invalid_token_ttl_seconds', 300)
        );

        $claims = [
            'iss' => (string) config('license_manager.jwt.issuer'),
            'iat' => $issuedAt,
            'exp' => $validUntil,
            'status' => $decision->status,
            'reason' => $decision->reason === LicenseValidationReason::NONE ? null : $decision->reason->value,
            'valid_until' => $validUntil,
            'instance_id' => $decision->instanceId,
            'domain' => $decision->domain,
            'product_id' => $decision->productId,
        ];

        $response = new PublicLicenseVerifyResponseData(
            status: $decision->status,
            reason: $decision->reason === LicenseValidationReason::NONE ? null : $decision->reason->value,
            valid_until: $validUntil,
            issued_at: $issuedAt,
            instance_id: $decision->instanceId,
            domain: $decision->domain,
            product_id: $decision->productId,
            token: $this->licenseJwtService->issue($claims),
        );

        $this->licenseCheckLogger->log(
            instance: $decision->instance,
            result: $decision->status === 'valid' ? LicenseCheckResult::VALID : LicenseCheckResult::INVALID,
            reason: $response->reason,
            requestPayload: [
                ...$payload,
                'ip' => $ipAddress,
                'user_agent' => $userAgent,
            ],
            responsePayload: $response->toArray(),
        );

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(array $payload): array
    {
        $validator = Validator::make($payload, [
            'purchase_code' => ['required', 'string', 'max:255'],
            'product_id' => ['nullable', 'integer', 'min:1', 'required_without:envato_item_id'],
            'envato_item_id' => ['nullable', 'integer', 'min:1', 'required_without:product_id'],
            'instance_id' => ['required', 'uuid'],
            'domain' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url', 'max:2048'],
            'app_version' => ['nullable', 'string', 'max:120'],
            'signature_proof' => ['nullable', 'string', 'max:1024'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildInvalidBadRequestResponse(array $payload): PublicLicenseVerifyResponseData
    {
        $issuedAt = (int) now()->timestamp;
        $validUntil = $issuedAt + (int) config('license_manager.invalid_token_ttl_seconds', 300);
        $instanceId = is_string(data_get($payload, 'instance_id')) ? (string) data_get($payload, 'instance_id') : '';
        $domain = is_string(data_get($payload, 'domain')) ? (string) data_get($payload, 'domain') : '';
        $productId = is_numeric(data_get($payload, 'product_id'))
            ? (int) data_get($payload, 'product_id')
            : (is_numeric(data_get($payload, 'envato_item_id')) ? (int) data_get($payload, 'envato_item_id') : 0);

        $claims = [
            'iss' => (string) config('license_manager.jwt.issuer'),
            'iat' => $issuedAt,
            'exp' => $validUntil,
            'status' => 'invalid',
            'reason' => LicenseValidationReason::BAD_REQUEST->value,
            'valid_until' => $validUntil,
            'instance_id' => $instanceId,
            'domain' => $domain,
            'product_id' => $productId,
        ];

        return new PublicLicenseVerifyResponseData(
            status: 'invalid',
            reason: LicenseValidationReason::BAD_REQUEST->value,
            valid_until: $validUntil,
            issued_at: $issuedAt,
            instance_id: $instanceId,
            domain: $domain,
            product_id: $productId,
            token: $this->licenseJwtService->issue($claims),
        );
    }
}
