<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\LicenseValidationReason;
use App\Services\MockPurchaseValidator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MockPurchaseValidatorTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixturePath = storage_path('framework/testing/envato-mock/fixtures-unit.json');

        config()->set('license_manager.envato_mock.mode', true);
        config()->set('license_manager.envato_mock.allowed_prefixes', ['MOCK-', 'TEST-']);
        config()->set('license_manager.envato_mock.seed', null);
        config()->set('license_manager.envato_mock.allowed_item_ids', []);
        config()->set('license_manager.envato_mock.allowed_product_ids', []);
        config()->set('license_manager.envato_mock.fixture_path', $this->fixturePath);

        File::ensureDirectoryExists(dirname($this->fixturePath));
        File::delete($this->fixturePath);
    }

    public function test_mock_validator_accepts_valid_mock_codes_by_prefix(): void
    {
        $validator = new MockPurchaseValidator();

        $result = $validator->validate('MOCK-VALID-001', 1001, 55);

        $this->assertTrue($result->valid);
        $this->assertSame(LicenseValidationReason::NONE, $result->reason);
        $this->assertSame(1001, $result->envatoItemId);
        $this->assertSame('mock', $result->source);
    }

    public function test_fixture_based_allow_and_deny_reasons_are_mapped(): void
    {
        $this->writeFixtures([
            [
                'purchase_code' => 'FIXTURE-ALLOW-001',
                'valid' => true,
                'buyer' => 'qa-buyer',
                'supported_envato_item_id_list' => [3001],
                'product_ids' => [701],
            ],
            [
                'purchase_code' => 'FIXTURE-REFUND-001',
                'valid' => false,
                'reason' => 'refund',
            ],
        ]);

        $validator = new MockPurchaseValidator();

        $allowResult = $validator->validate('FIXTURE-ALLOW-001', 3001, 701);
        $denyResult = $validator->validate('FIXTURE-REFUND-001', 3001, 701);

        $this->assertTrue($allowResult->valid);
        $this->assertSame(LicenseValidationReason::NONE, $allowResult->reason);
        $this->assertSame('qa-buyer', $allowResult->buyer);

        $this->assertFalse($denyResult->valid);
        $this->assertSame(LicenseValidationReason::REFUND, $denyResult->reason);
    }

    public function test_seed_generation_is_deterministic(): void
    {
        config()->set('license_manager.envato_mock.allowed_prefixes', []);
        config()->set('license_manager.envato_mock.seed', 'stable-seed-value');

        $firstRun = (new MockPurchaseValidator())->validate('SEED-CODE-001', null, null);
        $secondRun = (new MockPurchaseValidator())->validate('SEED-CODE-001', null, null);

        $this->assertSame($firstRun->valid, $secondRun->valid);
        $this->assertSame($firstRun->reason, $secondRun->reason);
        $this->assertSame($firstRun->envatoItemId, $secondRun->envatoItemId);
        $this->assertSame($firstRun->buyer, $secondRun->buyer);
        $this->assertSame($firstRun->supportedUntil, $secondRun->supportedUntil);
        $this->assertSame($firstRun->matchedBy, $secondRun->matchedBy);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fixtures
     */
    private function writeFixtures(array $fixtures): void
    {
        File::put(
            $this->fixturePath,
            json_encode(['fixtures' => $fixtures], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }
}
