<?php

declare(strict_types=1);

namespace App\Actions\LicenseManagement;

use App\Data\Requests\PublicLicenseDeactivateRequestData;
use App\Data\Responses\PublicLicenseDeactivateResponseData;
use App\Enums\LicenseCheckResult;
use App\Enums\LicenseValidationReason;
use App\Services\LicenseCheckLoggerService;
use App\Services\LicenseJwtService;
use App\Services\LicenseManagementService;
use App\Support\LicenseRequestNormalizer;
use InvalidArgumentException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

readonly class DeactivatePublicLicenseAction
{
    public function __construct(
        private LicenseManagementService $licenseManagementService,
        private LicenseJwtService $licenseJwtService,
        private LicenseCheckLoggerService $licenseCheckLogger,
        private LicenseRequestNormalizer $requestNormalizer,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload, string $ipAddress, ?string $userAgent): PublicLicenseDeactivateResponseData
    {
        try {
            $validated = $this->validate($payload);

            $requestData = new PublicLicenseDeactivateRequestData(
                purchaseCode: (string) data_get($validated, 'purchase_code'),
                productId: data_get($validated, 'product_id') !== null ? (int) data_get($validated, 'product_id') : null,
                envatoItemId: data_get($validated, 'envato_item_id') !== null
                    ? (int) data_get($validated, 'envato_item_id')
                    : null,
                instanceId: (string) data_get($validated, 'instance_id'),
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

        $decision = $this->licenseManagementService->deactivate($requestData);
        $issuedAt = (int) now()->timestamp;
        $validUntil = $issuedAt + (
            $decision->success
                ? (int) config('license_manager.token_ttl_seconds', 3600)
                : (int) config('license_manager.invalid_token_ttl_seconds', 300)
        );
        $domain = $decision->success
            ? $this->resolveTokenDomain(
                payload: $validated,
                instanceDomain: $decision->instance?->domain,
                instanceAppUrl: $decision->instance?->app_url,
            )
            : '';

        $claims = [
            'iss' => (string) config('license_manager.jwt.issuer'),
            'iat' => $issuedAt,
            'exp' => $validUntil,
            'status' => $decision->success ? 'valid' : 'invalid',
            'success' => $decision->success,
            'reason' => $decision->reason->value,
            'valid_until' => $validUntil,
            'instance_id' => $decision->instanceId,
            'domain' => $domain,
            'product_id' => $decision->productId,
        ];

        $response = new PublicLicenseDeactivateResponseData(
            success: $decision->success,
            reason: $decision->reason->value,
            issued_at: $issuedAt,
            valid_until: $validUntil,
            instance_id: $decision->instanceId,
            product_id: $decision->productId,
            token: $this->licenseJwtService->issue($claims),
        );

        $this->licenseCheckLogger->log(
            instance: $decision->instance,
            result: $decision->success ? LicenseCheckResult::VALID : LicenseCheckResult::INVALID,
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
            'purchase_code' => ['required', 'string', 'max:120'],
            'product_id' => ['nullable', 'integer', 'min:1', 'required_without:envato_item_id'],
            'envato_item_id' => ['nullable', 'integer', 'min:1', 'required_without:product_id'],
            'instance_id' => ['required', 'uuid'],
            'domain' => ['nullable', 'string', 'max:255'],
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
    private function buildInvalidBadRequestResponse(array $payload): PublicLicenseDeactivateResponseData
    {
        $issuedAt = (int) now()->timestamp;
        $validUntil = $issuedAt + (int) config('license_manager.invalid_token_ttl_seconds', 300);
        $instanceId = is_string(data_get($payload, 'instance_id')) ? (string) data_get($payload, 'instance_id') : '';
        $productId = is_numeric(data_get($payload, 'product_id'))
            ? (int) data_get($payload, 'product_id')
            : (is_numeric(data_get($payload, 'envato_item_id')) ? (int) data_get($payload, 'envato_item_id') : 0);

        $claims = [
            'iss' => (string) config('license_manager.jwt.issuer'),
            'iat' => $issuedAt,
            'exp' => $validUntil,
            'status' => 'invalid',
            'success' => false,
            'reason' => LicenseValidationReason::BAD_REQUEST->value,
            'valid_until' => $validUntil,
            'instance_id' => $instanceId,
            'domain' => '',
            'product_id' => $productId,
        ];

        return new PublicLicenseDeactivateResponseData(
            success: false,
            reason: LicenseValidationReason::BAD_REQUEST->value,
            issued_at: $issuedAt,
            valid_until: $validUntil,
            instance_id: $instanceId,
            product_id: $productId,
            token: $this->licenseJwtService->issue($claims),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTokenDomain(array $payload, ?string $instanceDomain, ?string $instanceAppUrl): string
    {
        $candidates = [];
        $payloadDomain = data_get($payload, 'domain');

        if (is_string($payloadDomain) && trim($payloadDomain) !== '') {
            $candidates[] = $payloadDomain;
        }

        if (is_string($instanceDomain) && trim($instanceDomain) !== '') {
            $candidates[] = $instanceDomain;
        }

        if (is_string($instanceAppUrl) && trim($instanceAppUrl) !== '') {
            $appUrlHost = parse_url($instanceAppUrl, PHP_URL_HOST);

            if (is_string($appUrlHost) && trim($appUrlHost) !== '') {
                $candidates[] = $appUrlHost;
            }
        }

        foreach ($candidates as $candidate) {
            try {
                return $this->requestNormalizer->normalizeDomain($candidate);
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        return '';
    }
}
