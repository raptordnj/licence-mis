<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseVerifyRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('license_manager.envato_mock.mode', true);
        config()->set('license_manager.envato_mock.allowed_prefixes', ['valid-']);
        config()->set('services.license.hmac_key', 'testing-hmac-key');
        config()->set('services.license.verify_rate_limit', 2);
    }

    public function test_verify_endpoint_is_rate_limited_by_ip_purchase_and_item(): void
    {
        $payload = [
            'purchase_code' => 'valid-rate-limit',
            'domain' => 'ratelimit.test',
            'item_id' => 1000,
        ];

        $this->postJson('/api/v1/licenses/verify', $payload)->assertOk();
        $this->postJson('/api/v1/licenses/verify', $payload)->assertOk();

        $third = $this->postJson('/api/v1/licenses/verify', $payload);

        $third->assertStatus(429);
        $third->assertJsonPath('error.code', 'RATE_LIMITED');
    }
}
