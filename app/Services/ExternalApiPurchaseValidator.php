<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Domain\ValidationResultDTO;
use App\Enums\LicenseValidationReason;
use App\Exceptions\EnvatoUnavailableException;
use App\Services\Contracts\EnvatoPurchaseValidatorInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

readonly class ExternalApiPurchaseValidator implements EnvatoPurchaseValidatorInterface
{
    private const VERIFY_ENDPOINT = '/api/v1/licenses/verify';

    public function __construct(
        private EnvatoPurchaseValidatorInterface $envatoValidator,
    ) {
    }

    public function validate(string $purchaseCode, ?int $envatoItemId, ?int $productId): ValidationResultDTO
    {
        try {
            return $this->envatoValidator->validate($purchaseCode, $envatoItemId, $productId);
        } catch (EnvatoUnavailableException $exception) {
            Log::info('Envato API unavailable, attempting external API validation', [
                'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                'error' => $exception->getMessage(),
            ]);

            return $this->validateViaExternalApi($purchaseCode, $envatoItemId, $productId);
        }
    }

    private function validateViaExternalApi(
        string $purchaseCode,
        ?int $envatoItemId,
        ?int $productId,
    ): ValidationResultDTO {
        $apiUrl = trim((string) config('services.external_license_api.url'));

        if ($apiUrl === '') {
            Log::warning('External API not configured', [
                'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
            ]);

            throw new EnvatoUnavailableException('External license API URL is not configured.');
        }

        try {
            $payload = [
                'purchase_code' => $purchaseCode,
                'domain' => $this->resolveVerificationDomain(),
                'item_id' => $envatoItemId,
            ];

            $response = $this->externalApiRequest($apiUrl)
                ->post(self::VERIFY_ENDPOINT, array_filter($payload, static fn ($value): bool => $value !== null));

            if ($response->status() === 401 || $response->status() === 403) {
                Log::warning('External API authentication failed', [
                    'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                    'status' => $response->status(),
                ]);

                throw new EnvatoUnavailableException('External API authentication failed.');
            }

            if ($response->status() === 404) {
                return ValidationResultDTO::invalidResult(
                    reason: LicenseValidationReason::NOT_FOUND,
                    source: 'external_api',
                    matchedBy: 'external_api_not_found',
                );
            }

            $responseBody = $response->json();
            if (! is_array($responseBody)) {
                throw new EnvatoUnavailableException('Invalid external API response format.');
            }

            if (! $response->successful()) {
                $mappedFailureReason = $this->mapRemoteReason(
                    (string) data_get($responseBody, 'error.code', ''),
                    (string) data_get($responseBody, 'data.reason', ''),
                );

                if ($mappedFailureReason !== null) {
                    return ValidationResultDTO::invalidResult(
                        reason: $mappedFailureReason,
                        source: 'external_api',
                        matchedBy: 'external_api_invalid',
                    );
                }

                throw new EnvatoUnavailableException('External API error: '.$response->status());
            }

            if (data_get($responseBody, 'success') !== true) {
                $mappedFailureReason = $this->mapRemoteReason(
                    (string) data_get($responseBody, 'error.code', ''),
                    (string) data_get($responseBody, 'data.reason', ''),
                );

                return ValidationResultDTO::invalidResult(
                    reason: $mappedFailureReason ?? LicenseValidationReason::NOT_FOUND,
                    source: 'external_api',
                    matchedBy: 'external_api_invalid',
                );
            }

            $data = data_get($responseBody, 'data');
            if (! is_array($data)) {
                throw new EnvatoUnavailableException('External API response data is missing.');
            }

            $status = mb_strtolower(trim((string) data_get($data, 'status', 'invalid')));
            if ($status !== 'valid') {
                $mappedFailureReason = $this->mapRemoteReason(
                    (string) data_get($responseBody, 'error.code', ''),
                    (string) data_get($data, 'reason', ''),
                );

                return ValidationResultDTO::invalidResult(
                    reason: $mappedFailureReason ?? LicenseValidationReason::NOT_FOUND,
                    source: 'external_api',
                    matchedBy: 'external_api_invalid',
                );
            }

            $resolvedItemId = is_numeric(data_get($data, 'envato_item_id'))
                ? (int) data_get($data, 'envato_item_id')
                : (is_numeric(data_get($data, 'item_id')) ? (int) data_get($data, 'item_id') : $envatoItemId);

            if ($envatoItemId !== null && $resolvedItemId !== null && $resolvedItemId !== $envatoItemId) {
                return ValidationResultDTO::invalidResult(
                    reason: LicenseValidationReason::NOT_FOUND,
                    source: 'external_api',
                    matchedBy: 'external_api_item_mismatch',
                );
            }

            $boundDomain = trim((string) data_get($data, 'bound_domain', ''));

            return ValidationResultDTO::validResult(
                envatoItemId: $resolvedItemId,
                buyer: is_string(data_get($data, 'buyer')) ? (string) data_get($data, 'buyer') : null,
                supportedUntil: is_string(data_get($data, 'supported_until'))
                    ? (string) data_get($data, 'supported_until')
                    : null,
                itemName: is_string(data_get($data, 'item_name')) ? (string) data_get($data, 'item_name') : null,
                maxActivations: is_numeric(data_get($data, 'max_activations'))
                    ? (int) data_get($data, 'max_activations')
                    : null,
                domainRestrictions: $boundDomain !== '' ? [$boundDomain] : [],
                source: 'external_api',
                matchedBy: 'external_api_match',
                rawPayload: $responseBody,
            );
        } catch (EnvatoUnavailableException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::error('External API validation failed', [
                'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                'error' => $exception->getMessage(),
            ]);

            throw new EnvatoUnavailableException('External API validation failed: '.$exception->getMessage());
        }
    }

    private function externalApiRequest(string $apiUrl): PendingRequest
    {
        $apiKey = trim((string) config('services.external_license_api.key'));

        $request = Http::baseUrl(rtrim($apiUrl, '/'))
            ->acceptJson()
            ->asJson()
            ->timeout(10)
            ->retry(2, 200, throw: false);

        if ($apiKey !== '') {
            return $request->withToken($apiKey);
        }

        return $request;
    }

    private function resolveVerificationDomain(): string
    {
        $appUrl = trim((string) config('app.url', ''));
        $host = is_string(parse_url($appUrl, PHP_URL_HOST))
            ? (string) parse_url($appUrl, PHP_URL_HOST)
            : '';

        $normalized = mb_strtolower(trim($host));

        if ($normalized === '') {
            return 'localhost';
        }

        if (str_starts_with($normalized, 'www.')) {
            return mb_substr($normalized, 4);
        }

        return $normalized;
    }

    private function mapRemoteReason(string $errorCode, string $statusReason): ?LicenseValidationReason
    {
        $normalizedErrorCode = mb_strtolower(trim($errorCode));
        $normalizedStatusReason = mb_strtolower(trim($statusReason));

        $reason = $normalizedStatusReason !== '' ? $normalizedStatusReason : $normalizedErrorCode;

        return match ($reason) {
            'revoke', 'revoked', 'license_revoked' => LicenseValidationReason::REVOKED,
            'refund', 'refunded', 'chargeback' => LicenseValidationReason::REFUND,
            'limit_reached', 'activation_limit_reached' => LicenseValidationReason::LIMIT_REACHED,
            'domain_mismatch' => LicenseValidationReason::DOMAIN_MISMATCH,
            'bad_request', 'validation_error' => LicenseValidationReason::BAD_REQUEST,
            'not_found', 'purchase_not_found', 'license_not_found', 'purchase_invalid' => LicenseValidationReason::NOT_FOUND,
            'envato_unavailable', 'internal_error' => null,
            default => null,
        };
    }

    private function maskPurchaseCode(string $purchaseCode): string
    {
        $length = mb_strlen($purchaseCode);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return mb_substr($purchaseCode, 0, 4)
            . str_repeat('*', $length - 8)
            . mb_substr($purchaseCode, -4);
    }
}
