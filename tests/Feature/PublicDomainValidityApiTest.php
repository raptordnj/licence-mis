<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LicenseInstanceStatus;
use App\Enums\LicenseStatus;
use App\Enums\ProductStatus;
use App\Models\License;
use App\Models\LicenseInstance;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDomainValidityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_validity_returns_true_for_active_bound_domain(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        License::factory()->forProduct($product)->create([
            'status' => LicenseStatus::VALID,
            'bound_domain' => 'licensed.example.com',
        ]);

        $response = $this->postJson('/api/licenses/domain-validity', [
            'domain' => 'https://www.licensed.example.com/api/v1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.valid', true);
        $response->assertJsonPath('data.domain', 'licensed.example.com');
        $response->assertJsonPath('data.reason', null);
    }

    public function test_domain_validity_returns_true_for_active_instance_domain(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        $license = License::factory()->forProduct($product)->create([
            'status' => LicenseStatus::ACTIVE,
            'bound_domain' => null,
        ]);

        LicenseInstance::query()->create([
            'license_id' => $license->id,
            'instance_id' => (string) fake()->uuid(),
            'domain' => 'instance.example.com',
            'app_url' => 'https://instance.example.com',
            'status' => LicenseInstanceStatus::ACTIVE,
            'activated_at' => now(),
            'last_seen_at' => now(),
        ]);

        $response = $this->postJson('/api/licenses/domain-validity', [
            'domain' => 'instance.example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.valid', true);
        $response->assertJsonPath('data.domain', 'instance.example.com');
        $response->assertJsonPath('data.reason', null);
    }

    public function test_domain_validity_returns_false_when_domain_is_not_licensed(): void
    {
        $product = Product::factory()->create([
            'status' => ProductStatus::ACTIVE,
        ]);

        License::factory()->forProduct($product)->create([
            'status' => LicenseStatus::REVOKED,
            'bound_domain' => 'revoked.example.com',
        ]);

        $response = $this->postJson('/api/licenses/domain-validity', [
            'domain' => 'revoked.example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.valid', false);
        $response->assertJsonPath('data.domain', 'revoked.example.com');
        $response->assertJsonPath('data.reason', 'not_licensed');
    }

    public function test_domain_validity_returns_invalid_domain_reason_for_malformed_input(): void
    {
        $response = $this->postJson('/api/licenses/domain-validity', [
            'domain' => '://bad-domain',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.valid', false);
        $response->assertJsonPath('data.domain', null);
        $response->assertJsonPath('data.reason', 'invalid_domain');
    }
}
