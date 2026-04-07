<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\LicenseValidationReason;
use App\Exceptions\EnvatoUnavailableException;
use App\Services\ExternalApiPurchaseValidator;
use App\Services\Contracts\EnvatoPurchaseValidatorInterface;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalApiPurchaseValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.external_license_api.url', 'https://license-server.envaysoft.com');
        config()->set('services.external_license_api.key', '');
        config()->set('app.url', 'https://licence-mis.local');
    }

    public function test_it_falls_back_to_license_server_and_returns_valid_result(): void
    {
        Http::fake([
            'https://license-server.envaysoft.com/api/v1/licenses/verify' => Http::response([
                'success' => true,
                'data' => [
                    'status' => 'valid',
                    'envato_item_id' => 23014826,
                    'bound_domain' => 'licence-mis.local',
                ],
            ], 200),
        ]);

        $validator = new ExternalApiPurchaseValidator($this->failingEnvatoValidator());

        $result = $validator->validate('PURCHASE-CODE-VALID-001', 23014826, 1);

        $this->assertTrue($result->valid);
        $this->assertSame(LicenseValidationReason::NONE, $result->reason);
        $this->assertSame(23014826, $result->envatoItemId);
        $this->assertSame('external_api', $result->source);
        $this->assertSame('external_api_match', $result->matchedBy);
    }

    public function test_it_maps_domain_mismatch_response_from_license_server(): void
    {
        Http::fake([
            'https://license-server.envaysoft.com/api/v1/licenses/verify' => Http::response([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'DOMAIN_MISMATCH',
                    'message' => 'Domain mismatch.',
                ],
            ], 409),
        ]);

        $validator = new ExternalApiPurchaseValidator($this->failingEnvatoValidator());

        $result = $validator->validate('PURCHASE-CODE-DOMAIN-001', 23014826, 1);

        $this->assertFalse($result->valid);
        $this->assertSame(LicenseValidationReason::DOMAIN_MISMATCH, $result->reason);
        $this->assertSame('external_api', $result->source);
        $this->assertSame('external_api_invalid', $result->matchedBy);
    }

    public function test_it_throws_unavailable_when_license_server_is_unavailable(): void
    {
        Http::fake([
            'https://license-server.envaysoft.com/api/v1/licenses/verify' => Http::response([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'ENVATO_UNAVAILABLE',
                    'message' => 'Verification provider is currently unavailable.',
                ],
            ], 503),
        ]);

        $validator = new ExternalApiPurchaseValidator($this->failingEnvatoValidator());

        $this->expectException(EnvatoUnavailableException::class);

        $validator->validate('PURCHASE-CODE-UNAVAILABLE-001', 23014826, 1);
    }

    private function failingEnvatoValidator(): EnvatoPurchaseValidatorInterface
    {
        return new class implements EnvatoPurchaseValidatorInterface
        {
            public function validate(string $purchaseCode, ?int $envatoItemId, ?int $productId): \App\Data\Domain\ValidationResultDTO
            {
                throw new EnvatoUnavailableException('Envato unavailable in test.');
            }
        };
    }
}
