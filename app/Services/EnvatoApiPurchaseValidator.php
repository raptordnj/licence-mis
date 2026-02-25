<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Domain\ValidationResultDTO;
use App\Enums\LicenseValidationReason;
use App\Exceptions\EnvatoUnavailableException;
use App\Services\Contracts\EnvatoPurchaseValidatorInterface;
use App\Services\Contracts\SensitiveSettingsStoreInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

readonly class EnvatoApiPurchaseValidator implements EnvatoPurchaseValidatorInterface
{
    public function __construct(private SensitiveSettingsStoreInterface $sensitiveSettingsStore)
    {
    }

    public function validate(string $purchaseCode, ?int $envatoItemId, ?int $productId): ValidationResultDTO
    {
        unset($productId);

        $purchase = $this->fetchPurchase($purchaseCode);

        if (! is_array($purchase)) {
            return ValidationResultDTO::invalidResult(
                reason: LicenseValidationReason::NOT_FOUND,
                source: 'envato_api',
                matchedBy: 'envato_api_not_found',
            );
        }

        $resolvedItemId = is_numeric(data_get($purchase, 'item.id'))
            ? (int) data_get($purchase, 'item.id')
            : null;

        if ($envatoItemId !== null && $resolvedItemId !== null && $resolvedItemId !== $envatoItemId) {
            return ValidationResultDTO::invalidResult(
                reason: LicenseValidationReason::NOT_FOUND,
                source: 'envato_api',
                matchedBy: 'envato_api_item_mismatch',
            );
        }

        return ValidationResultDTO::validResult(
            envatoItemId: $resolvedItemId ?? $envatoItemId,
            buyer: $this->resolveBuyer($purchase),
            supportedUntil: is_string(data_get($purchase, 'supported_until'))
                ? (string) data_get($purchase, 'supported_until')
                : null,
            itemName: is_string(data_get($purchase, 'item.name'))
                ? (string) data_get($purchase, 'item.name')
                : null,
            maxActivations: null,
            domainRestrictions: [],
            source: 'envato_api',
            matchedBy: 'envato_api_match',
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchPurchase(string $purchaseCode): ?array
    {
        $cacheTtl = (int) config('services.envato.cache_ttl_seconds', 300);

        /** @var array<string, mixed>|null $purchase */
        $purchase = Cache::remember(
            "envato:verify:{$purchaseCode}",
            $cacheTtl,
            function () use ($purchaseCode): ?array {
                $token = $this->sensitiveSettingsStore->getEnvatoToken() ?: (string) config('services.envato.token');

                if ($token === '') {
                    throw new EnvatoUnavailableException('Envato token is not configured.');
                }

                $authorizationRejected = false;
                $authorizationRejections = [];

                foreach ($this->verificationEndpoints($purchaseCode) as $endpoint) {
                    $response = Http::baseUrl((string) config('services.envato.base_url'))
                        ->withToken($token)
                        ->acceptJson()
                        ->timeout(10)
                        ->retry(2, 200, throw: false)
                        ->get($endpoint['path'], $endpoint['query']);

                    if (in_array($response->status(), [401, 403], true)) {
                        $responseError = $this->extractResponseErrorMessage($response->json(), $response->body());
                        $authorizationRejections[] = [
                            'endpoint' => $endpoint['path'],
                            'error' => $responseError,
                        ];

                        Log::warning('Envato endpoint rejected token for purchase verification.', [
                            'endpoint' => $endpoint['path'],
                            'status' => $response->status(),
                            'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
                            'envato_error' => $responseError,
                        ]);

                        $authorizationRejected = true;

                        continue;
                    }

                    if ($response->status() === 404) {
                        return null;
                    }

                    if (! $response->successful()) {
                        continue;
                    }

                    $purchasePayload = $this->extractPurchasePayload($response->json());

                    if (! is_array($purchasePayload)) {
                        return null;
                    }

                    return $purchasePayload;
                }

                if ($authorizationRejected) {
                    $joinedReasons = collect($authorizationRejections)
                        ->map(static function (array $rejection): string {
                            $endpoint = is_string(data_get($rejection, 'endpoint')) ? (string) data_get($rejection, 'endpoint') : 'unknown';
                            $error = is_string(data_get($rejection, 'error')) ? (string) data_get($rejection, 'error') : 'permission denied';

                            return "{$endpoint}: {$error}";
                        })
                        ->implode(' | ');

                    throw new EnvatoUnavailableException(
                        $joinedReasons !== ''
                            ? "Envato rejected token permissions. {$joinedReasons}"
                            : 'Envato token is valid but missing one of the required Envato scopes (sale:verify, purchase:verify, purchase:list).',
                    );
                }

                throw new EnvatoUnavailableException();
            },
        );

        return $purchase;
    }

    /**
     * @return array<int, array{path: string, query: array<string, string>}>
     */
    private function verificationEndpoints(string $purchaseCode): array
    {
        return [
            [
                'path' => '/author/sale',
                'query' => [
                    'code' => $purchaseCode,
                ],
            ],
            [
                'path' => '/buyer/purchase',
                'query' => [
                    'code' => $purchaseCode,
                ],
            ],
            [
                'path' => '/buyer/list-purchases',
                'query' => [
                    'filter_by' => 'code',
                    'code' => $purchaseCode,
                ],
            ],
        ];
    }

    /**
     * @param  mixed  $payload
     * @return array<string, mixed>|null
     */
    private function extractPurchasePayload(mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $matchedEntry = data_get($payload, 'matches.0');

        if (is_array($matchedEntry)) {
            /** @var array<string, mixed> $matchedEntry */
            return $matchedEntry;
        }

        if (array_is_list($payload) && isset($payload[0]) && is_array($payload[0])) {
            /** @var array<string, mixed> $first */
            $first = $payload[0];

            return $first;
        }

        $dataPayload = data_get($payload, 'data');

        if (is_array($dataPayload) && (is_array(data_get($dataPayload, 'item')) || is_string(data_get($dataPayload, 'buyer')))) {
            /** @var array<string, mixed> $dataPayload */
            return $dataPayload;
        }

        if (is_array(data_get($payload, 'item')) || is_string(data_get($payload, 'buyer'))) {
            /** @var array<string, mixed> $payload */
            return $payload;
        }

        return null;
    }

    private function maskPurchaseCode(string $purchaseCode): string
    {
        $length = mb_strlen($purchaseCode);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return mb_substr($purchaseCode, 0, 4)
            .str_repeat('*', $length - 8)
            .mb_substr($purchaseCode, -4);
    }

    /**
     * @param  mixed  $payload
     */
    private function extractResponseErrorMessage(mixed $payload, string $fallbackBody): string
    {
        $error = is_string(data_get($payload, 'error')) ? trim((string) data_get($payload, 'error')) : '';

        if ($error !== '') {
            return $error;
        }

        $message = is_string(data_get($payload, 'message')) ? trim((string) data_get($payload, 'message')) : '';

        if ($message !== '') {
            return $message;
        }

        $normalizedBody = trim($fallbackBody);

        if ($normalizedBody !== '') {
            return mb_substr($normalizedBody, 0, 180);
        }

        return 'permission denied';
    }

    /**
     * @param  array<string, mixed>  $purchase
     */
    private function resolveBuyer(array $purchase): ?string
    {
        foreach (['buyer', 'buyer_username', 'owner'] as $key) {
            $value = data_get($purchase, $key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
