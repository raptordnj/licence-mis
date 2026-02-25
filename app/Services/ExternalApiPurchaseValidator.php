<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Domain\ValidationResultDTO;
use App\Enums\LicenseValidationReason;
use App\Exceptions\EnvatoUnavailableException;
use App\Services\Contracts\EnvatoPurchaseValidatorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

readonly class ExternalApiPurchaseValidator implements EnvatoPurchaseValidatorInterface
{
    public function __construct(
        private EnvatoPurchaseValidatorInterface $envatoValidator,
    ) {
    }

    public function validate(string $purchaseCode, ?int $envatoItemId, ?int $productId): ValidationResultDTO
    {
        try {
            // First try Envato API validation
            return $this->envatoValidator->validate($purchaseCode, $envatoItemId, $productId);
        } catch (EnvatoUnavailableException $e) {
            Log::info('Envato API unavailable, attempting external API validation', [
                'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                'error' => $e->getMessage(),
            ]);

            // If Envato fails, try external API
            return $this->validateViaExternalApi($purchaseCode, $envatoItemId, $productId);
        }
    }

    /**
     * Validate purchase code via external API
     * Auto-registers the license if valid
     */
    private function validateViaExternalApi(
        string $purchaseCode,
        ?int $envatoItemId,
        ?int $productId,
    ): ValidationResultDTO {
        $apiUrl = (string) config('services.external_license_api.url');
        $apiKey = (string) config('services.external_license_api.key');

        if ($apiUrl === '' || $apiKey === '') {
            Log::warning('External API not configured', [
                'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
            ]);

            throw new EnvatoUnavailableException(
                'External license API is not configured.',
            );
        }

        try {
            $response = Http::baseUrl($apiUrl)
                ->withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Accept' => 'application/json',
                ])
                ->timeout(10)
                ->retry(2, 200, throw: false)
                ->post('/verify-purchase', [
                    'purchase_code' => $purchaseCode,
                    'envato_item_id' => $envatoItemId,
                    'product_id' => $productId,
                ]);

            if ($response->status() === 401 || $response->status() === 403) {
                Log::warning('External API authentication failed', [
                    'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                    'status' => $response->status(),
                ]);

                throw new EnvatoUnavailableException(
                    'External API authentication failed: ' . $response->body(),
                );
            }

            if ($response->status() === 404) {
                Log::info('Purchase code not found in external API', [
                    'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                ]);

                return ValidationResultDTO::invalidResult(
                    reason: LicenseValidationReason::NOT_FOUND,
                    source: 'external_api',
                    matchedBy: 'external_api_not_found',
                );
            }

            if (!$response->successful()) {
                Log::error('External API error', [
                    'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new EnvatoUnavailableException(
                    'External API error: ' . $response->status(),
                );
            }

            $data = $response->json();

            if (!is_array($data)) {
                Log::error('Invalid external API response format', [
                    'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                ]);

                throw new EnvatoUnavailableException('Invalid API response format');
            }

            // Check if verification was successful
            if (!data_get($data, 'valid') && data_get($data, 'valid') !== true) {
                Log::info('Purchase code invalid according to external API', [
                    'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                    'reason' => data_get($data, 'reason'),
                ]);

                return ValidationResultDTO::invalidResult(
                    reason: LicenseValidationReason::NOT_FOUND,
                    source: 'external_api',
                    matchedBy: 'external_api_invalid',
                );
            }

            // Extract validation data from external API
            $resolvedItemId = is_numeric(data_get($data, 'item_id'))
                ? (int) data_get($data, 'item_id')
                : $envatoItemId;

            if ($envatoItemId !== null && $resolvedItemId !== null && $resolvedItemId !== $envatoItemId) {
                Log::warning('Item ID mismatch in external API', [
                    'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                    'expected' => $envatoItemId,
                    'received' => $resolvedItemId,
                ]);

                return ValidationResultDTO::invalidResult(
                    reason: LicenseValidationReason::NOT_FOUND,
                    source: 'external_api',
                    matchedBy: 'external_api_item_mismatch',
                );
            }

            Log::info('Purchase code validated via external API', [
                'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                'buyer' => is_string(data_get($data, 'buyer')) ? 'present' : 'absent',
            ]);

            return ValidationResultDTO::validResult(
                envatoItemId: $resolvedItemId ?? $envatoItemId,
                buyer: is_string(data_get($data, 'buyer'))
                    ? (string) data_get($data, 'buyer')
                    : null,
                supportedUntil: is_string(data_get($data, 'support_until'))
                    ? (string) data_get($data, 'support_until')
                    : null,
                itemName: is_string(data_get($data, 'item_name'))
                    ? (string) data_get($data, 'item_name')
                    : null,
                maxActivations: is_numeric(data_get($data, 'max_activations'))
                    ? (int) data_get($data, 'max_activations')
                    : null,
                domainRestrictions: is_array(data_get($data, 'domain_restrictions'))
                    ? (array) data_get($data, 'domain_restrictions')
                    : [],
                source: 'external_api',
                matchedBy: 'external_api_match',
            );
        } catch (EnvatoUnavailableException) {
            throw;
        } catch (\Exception $exception) {
            Log::error('External API validation failed', [
                'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                'error' => $exception->getMessage(),
            ]);

            throw new EnvatoUnavailableException(
                'External API validation failed: ' . $exception->getMessage(),
            );
        }
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
