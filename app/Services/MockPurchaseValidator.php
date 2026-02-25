<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Domain\ValidationResultDTO;
use App\Enums\LicenseValidationReason;
use App\Services\Contracts\EnvatoPurchaseValidatorInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

class MockPurchaseValidator implements EnvatoPurchaseValidatorInterface
{
    private const int DEFAULT_ITEM_ID = 1000;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $fixturesByCode = null;

    public function validate(string $purchaseCode, ?int $envatoItemId, ?int $productId): ValidationResultDTO
    {
        if (
            mb_strtolower((string) config('app.env', 'production')) === 'production'
            && (bool) config('license_manager.envato_mock.fail_closed_in_prod', true)
        ) {
            Log::critical('Envato mock validator was invoked in production while fail-closed mode is enabled.');

            throw new RuntimeException(
                'Mock purchase validation is blocked in production while fail-closed mode is enabled.',
            );
        }

        $fixtureResult = $this->validateFromFixture($purchaseCode, $envatoItemId, $productId);

        if ($fixtureResult instanceof ValidationResultDTO) {
            $this->logDecision($purchaseCode, $fixtureResult);

            return $fixtureResult;
        }

        $prefixResult = $this->validateFromPrefix($purchaseCode, $envatoItemId, $productId);

        if ($prefixResult instanceof ValidationResultDTO) {
            $this->logDecision($purchaseCode, $prefixResult);

            return $prefixResult;
        }

        $seedResult = $this->validateFromSeed($purchaseCode, $envatoItemId, $productId);

        if ($seedResult instanceof ValidationResultDTO) {
            $this->logDecision($purchaseCode, $seedResult);

            return $seedResult;
        }

        $defaultResult = ValidationResultDTO::invalidResult(
            reason: LicenseValidationReason::NOT_FOUND,
            source: 'mock',
            matchedBy: 'default_not_found',
        );

        $this->logDecision($purchaseCode, $defaultResult);

        return $defaultResult;
    }

    private function validateFromPrefix(string $purchaseCode, ?int $envatoItemId, ?int $productId): ?ValidationResultDTO
    {
        $prefixes = $this->configuredPrefixes();

        $matchedPrefix = null;

        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && str_starts_with(mb_strtoupper($purchaseCode), mb_strtoupper($prefix))) {
                $matchedPrefix = $prefix;

                break;
            }
        }

        if (! is_string($matchedPrefix)) {
            return null;
        }

        $allowedItemIds = $this->configuredAllowedItemIds();
        $allowedProductIds = $this->configuredAllowedProductIds();

        if (
            $envatoItemId !== null
            && $allowedItemIds !== []
            && ! in_array($envatoItemId, $allowedItemIds, true)
        ) {
            return ValidationResultDTO::invalidResult(
                reason: LicenseValidationReason::NOT_FOUND,
                source: 'mock',
                matchedBy: 'prefix_item_mismatch',
            );
        }

        if (
            $productId !== null
            && $allowedProductIds !== []
            && ! in_array($productId, $allowedProductIds, true)
        ) {
            return ValidationResultDTO::invalidResult(
                reason: LicenseValidationReason::NOT_FOUND,
                source: 'mock',
                matchedBy: 'prefix_product_mismatch',
            );
        }

        $resolvedItemId = $envatoItemId ?? $allowedItemIds[0] ?? self::DEFAULT_ITEM_ID;

        return ValidationResultDTO::validResult(
            envatoItemId: $resolvedItemId,
            buyer: 'mock-prefix-buyer',
            supportedUntil: '2099-12-31T23:59:59+00:00',
            itemName: 'Mock Prefix Item',
            maxActivations: null,
            domainRestrictions: [],
            source: 'mock',
            matchedBy: "prefix:{$matchedPrefix}",
        );
    }

    private function validateFromSeed(string $purchaseCode, ?int $envatoItemId, ?int $productId): ?ValidationResultDTO
    {
        $seed = trim((string) config('license_manager.envato_mock.seed', ''));

        if ($seed === '') {
            return null;
        }

        $hash = hash('sha256', "{$seed}|{$purchaseCode}");
        $isValid = (hexdec(substr($hash, 0, 2)) % 2) === 0;

        if (! $isValid) {
            return ValidationResultDTO::invalidResult(
                reason: LicenseValidationReason::NOT_FOUND,
                source: 'mock',
                matchedBy: 'seed_rejected',
            );
        }

        $allowedItemIds = $this->configuredAllowedItemIds();
        $allowedProductIds = $this->configuredAllowedProductIds();

        if (
            $envatoItemId !== null
            && $allowedItemIds !== []
            && ! in_array($envatoItemId, $allowedItemIds, true)
        ) {
            return ValidationResultDTO::invalidResult(
                reason: LicenseValidationReason::NOT_FOUND,
                source: 'mock',
                matchedBy: 'seed_item_mismatch',
            );
        }

        if (
            $productId !== null
            && $allowedProductIds !== []
            && ! in_array($productId, $allowedProductIds, true)
        ) {
            return ValidationResultDTO::invalidResult(
                reason: LicenseValidationReason::NOT_FOUND,
                source: 'mock',
                matchedBy: 'seed_product_mismatch',
            );
        }

        $resolvedItemId = $envatoItemId ?? $this->resolveSeedItemId($hash, $allowedItemIds);
        $validUntil = CarbonImmutable::parse('2030-01-01T00:00:00+00:00')
            ->addDays(hexdec(substr($hash, 2, 3)) % 365)
            ->toIso8601String();

        return ValidationResultDTO::validResult(
            envatoItemId: $resolvedItemId,
            buyer: 'seed-'.substr($hash, 0, 12),
            supportedUntil: $validUntil,
            itemName: 'Mock Seeded Item',
            maxActivations: null,
            domainRestrictions: [],
            source: 'mock',
            matchedBy: 'seed_accept',
        );
    }

    /**
     * @param  array<int, int>  $allowedItemIds
     */
    private function resolveSeedItemId(string $hash, array $allowedItemIds): int
    {
        if ($allowedItemIds !== []) {
            return $allowedItemIds[hexdec(substr($hash, 5, 2)) % count($allowedItemIds)];
        }

        return 100000 + (hexdec(substr($hash, 8, 4)) % 900000);
    }

    private function validateFromFixture(string $purchaseCode, ?int $envatoItemId, ?int $productId): ?ValidationResultDTO
    {
        $fixture = $this->loadFixturesByCode()[$purchaseCode] ?? null;

        if (! is_array($fixture)) {
            return null;
        }

        $fixtureItemIds = $this->extractIdList(
            $fixture,
            ['envato_item_ids', 'supported_envato_item_id_list', 'supported_envato_item_ids'],
            ['envato_item_id'],
        );
        $fixtureProductIds = $this->extractIdList(
            $fixture,
            ['product_ids', 'supported_product_ids', 'supported_product_id_list'],
            ['product_id'],
        );

        if (
            $envatoItemId !== null
            && $fixtureItemIds !== []
            && ! in_array($envatoItemId, $fixtureItemIds, true)
        ) {
            return ValidationResultDTO::invalidResult(
                reason: LicenseValidationReason::NOT_FOUND,
                source: 'mock',
                matchedBy: 'fixture_item_mismatch',
            );
        }

        if (
            $productId !== null
            && $fixtureProductIds !== []
            && ! in_array($productId, $fixtureProductIds, true)
        ) {
            return ValidationResultDTO::invalidResult(
                reason: LicenseValidationReason::NOT_FOUND,
                source: 'mock',
                matchedBy: 'fixture_product_mismatch',
            );
        }

        $reason = is_string(data_get($fixture, 'reason'))
            ? $this->mapReason((string) data_get($fixture, 'reason'), LicenseValidationReason::NOT_FOUND)
            : LicenseValidationReason::NOT_FOUND;

        $hasExplicitValidFlag = array_key_exists('valid', $fixture);
        $isValid = $hasExplicitValidFlag ? (bool) data_get($fixture, 'valid') : true;

        if (! $hasExplicitValidFlag && is_string(data_get($fixture, 'reason'))) {
            $isValid = false;
        }

        $maxActivations = $this->extractPositiveOrZeroInteger(data_get($fixture, 'max_activations'));

        if (is_int($maxActivations) && $maxActivations === 0) {
            return ValidationResultDTO::invalidResult(
                reason: LicenseValidationReason::LIMIT_REACHED,
                source: 'mock',
                matchedBy: 'fixture_max_activations_zero',
            );
        }

        if ((bool) data_get($fixture, 'limit_reached', false)) {
            return ValidationResultDTO::invalidResult(
                reason: LicenseValidationReason::LIMIT_REACHED,
                source: 'mock',
                matchedBy: 'fixture_limit_reached_flag',
            );
        }

        $expiresAt = data_get($fixture, 'expires_at');

        if (is_string($expiresAt) && $expiresAt !== '') {
            try {
                if (CarbonImmutable::parse($expiresAt)->isPast()) {
                    return ValidationResultDTO::invalidResult(
                        reason: $reason !== LicenseValidationReason::NOT_FOUND
                            ? $reason
                            : LicenseValidationReason::REVOKED,
                        source: 'mock',
                        matchedBy: 'fixture_expired',
                    );
                }
            } catch (\Throwable) {
                return ValidationResultDTO::invalidResult(
                    reason: LicenseValidationReason::BAD_REQUEST,
                    source: 'mock',
                    matchedBy: 'fixture_bad_expiry_format',
                );
            }
        }

        if (! $isValid) {
            return ValidationResultDTO::invalidResult(
                reason: $reason,
                source: 'mock',
                matchedBy: 'fixture_invalid_flag',
            );
        }

        $domainRestrictions = $this->extractDomainRestrictions($fixture);
        $resolvedItemId = $envatoItemId ?? $fixtureItemIds[0] ?? self::DEFAULT_ITEM_ID;

        return ValidationResultDTO::validResult(
            envatoItemId: $resolvedItemId,
            buyer: is_string(data_get($fixture, 'buyer')) ? (string) data_get($fixture, 'buyer') : null,
            supportedUntil: is_string(data_get($fixture, 'supported_until'))
                ? (string) data_get($fixture, 'supported_until')
                : (is_string($expiresAt) ? $expiresAt : null),
            itemName: is_string(data_get($fixture, 'item_name')) ? (string) data_get($fixture, 'item_name') : null,
            maxActivations: is_int($maxActivations) && $maxActivations > 0 ? $maxActivations : null,
            domainRestrictions: $domainRestrictions,
            source: 'mock',
            matchedBy: 'fixture_match',
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function loadFixturesByCode(): array
    {
        if (is_array($this->fixturesByCode)) {
            return $this->fixturesByCode;
        }

        $configuredPath = (string) config(
            'license_manager.envato_mock.fixture_path',
            storage_path('app/envato-mock/fixtures.json'),
        );
        $path = $this->resolveFixturePath($configuredPath);

        if (! is_file($path)) {
            $this->fixturesByCode = [];

            return $this->fixturesByCode;
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Log::error('Failed to decode Envato mock fixture file.', [
                'path' => $path,
            ]);

            $this->fixturesByCode = [];

            return $this->fixturesByCode;
        }

        if (! is_array($decoded)) {
            $this->fixturesByCode = [];

            return $this->fixturesByCode;
        }

        $records = data_get($decoded, 'fixtures');

        if (is_array($records)) {
            $decoded = $records;
        }

        $fixturesByCode = [];

        foreach ($decoded as $key => $record) {
            if (! is_array($record)) {
                continue;
            }

            $code = is_string(data_get($record, 'purchase_code'))
                ? trim((string) data_get($record, 'purchase_code'))
                : (is_string($key) ? trim($key) : '');

            if ($code === '') {
                continue;
            }

            $fixturesByCode[$code] = [
                ...$record,
                'purchase_code' => $code,
            ];
        }

        $this->fixturesByCode = $fixturesByCode;

        return $this->fixturesByCode;
    }

    private function resolveFixturePath(string $configuredPath): string
    {
        if (
            str_starts_with($configuredPath, '/')
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $configuredPath) === 1
        ) {
            return $configuredPath;
        }

        return base_path($configuredPath);
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @param  array<int, string>  $multiKeys
     * @param  array<int, string>  $singleKeys
     * @return array<int, int>
     */
    private function extractIdList(array $fixture, array $multiKeys, array $singleKeys): array
    {
        foreach ($multiKeys as $key) {
            $value = data_get($fixture, $key);

            if (is_array($value)) {
                $parsed = array_values(array_filter(array_map(
                    static function (mixed $id): ?int {
                        if (! is_numeric($id)) {
                            return null;
                        }

                        $intId = (int) $id;

                        return $intId > 0 ? $intId : null;
                    },
                    $value,
                )));

                if ($parsed !== []) {
                    return $parsed;
                }
            }
        }

        foreach ($singleKeys as $key) {
            $value = data_get($fixture, $key);

            if (is_numeric($value) && (int) $value > 0) {
                return [(int) $value];
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $fixture
     * @return array<int, string>
     */
    private function extractDomainRestrictions(array $fixture): array
    {
        $value = data_get($fixture, 'domain_restrictions');

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static function (mixed $domain): ?string {
                if (! is_string($domain)) {
                    return null;
                }

                $trimmed = trim($domain);

                return $trimmed !== '' ? $trimmed : null;
            },
            $value,
        )));
    }

    private function extractPositiveOrZeroInteger(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue >= 0 ? $intValue : null;
    }

    private function mapReason(?string $reason, LicenseValidationReason $default): LicenseValidationReason
    {
        return match ($reason) {
            LicenseValidationReason::REVOKED->value => LicenseValidationReason::REVOKED,
            LicenseValidationReason::REFUND->value => LicenseValidationReason::REFUND,
            LicenseValidationReason::LIMIT_REACHED->value => LicenseValidationReason::LIMIT_REACHED,
            LicenseValidationReason::DOMAIN_MISMATCH->value => LicenseValidationReason::DOMAIN_MISMATCH,
            LicenseValidationReason::NOT_FOUND->value => LicenseValidationReason::NOT_FOUND,
            LicenseValidationReason::BAD_REQUEST->value => LicenseValidationReason::BAD_REQUEST,
            default => $default,
        };
    }

    /**
     * @return array<int, string>
     */
    private function configuredPrefixes(): array
    {
        $configured = config('license_manager.envato_mock.allowed_prefixes', ['MOCK-']);

        if (! is_array($configured)) {
            return ['MOCK-'];
        }

        return array_values(array_filter(array_map(
            static function (mixed $prefix): ?string {
                if (! is_string($prefix)) {
                    return null;
                }

                $trimmed = trim($prefix);

                return $trimmed !== '' ? $trimmed : null;
            },
            $configured,
        )));
    }

    /**
     * @return array<int, int>
     */
    private function configuredAllowedItemIds(): array
    {
        return $this->configuredIntList(config('license_manager.envato_mock.allowed_item_ids', []));
    }

    /**
     * @return array<int, int>
     */
    private function configuredAllowedProductIds(): array
    {
        return $this->configuredIntList(config('license_manager.envato_mock.allowed_product_ids', []));
    }

    /**
     * @return array<int, int>
     */
    private function configuredIntList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static function (mixed $id): ?int {
                if (! is_numeric($id)) {
                    return null;
                }

                $intId = (int) $id;

                return $intId > 0 ? $intId : null;
            },
            $value,
        )));
    }

    private function logDecision(string $purchaseCode, ValidationResultDTO $result): void
    {
        Log::info('Envato purchase validation resolved by local mock mode.', [
            'mock_mode' => true,
            'purchase_code_hint' => $this->maskPurchaseCode($purchaseCode),
            'source' => $result->source,
            'matched_by' => $result->matchedBy,
            'status' => $result->valid ? 'valid' : 'invalid',
            'reason' => $result->reason->value,
        ]);
    }

    private function maskPurchaseCode(string $purchaseCode): string
    {
        $length = mb_strlen($purchaseCode);

        if ($length <= 4) {
            return str_repeat('*', max($length, 1));
        }

        if ($length <= 8) {
            return mb_substr($purchaseCode, 0, 2).str_repeat('*', $length - 4).mb_substr($purchaseCode, -2);
        }

        return mb_substr($purchaseCode, 0, 4).str_repeat('*', $length - 8).mb_substr($purchaseCode, -4);
    }
}
