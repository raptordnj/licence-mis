<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LicenseVerificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('license_manager.envato_mock.mode', true);
        config()->set('license_manager.envato_mock.allowed_prefixes', ['valid-']);
    }

    public function test_it_binds_domain_on_first_verification(): void
    {
        $response = $this->postJson('/api/v1/licenses/verify', [
            'purchase_code' => 'valid-first-bind',
            'domain' => 'https://www.Example.com/some/path',
            'item_id' => 1000,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('licenses', [
            'purchase_code' => 'valid-first-bind',
            'bound_domain' => 'example.com',
            'status' => LicenseStatus::ACTIVE->value,
        ]);
    }

    public function test_it_resolves_item_scope_from_product_id_when_item_id_is_missing(): void
    {
        $product = Product::factory()->create([
            'envato_item_id' => 1000,
        ]);

        $response = $this->postJson('/api/v1/licenses/verify', [
            'purchase_code' => 'valid-product-id-only',
            'domain' => 'product-id-only.example.com',
            'product_id' => $product->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $this->assertDatabaseHas('licenses', [
            'purchase_code' => 'valid-product-id-only',
            'bound_domain' => 'product-id-only.example.com',
            'envato_item_id' => 1000,
            'status' => LicenseStatus::ACTIVE->value,
        ]);
    }

    public function test_it_returns_domain_mismatch_for_different_domain(): void
    {
        $this->postJson('/api/v1/licenses/verify', [
            'purchase_code' => 'valid-domain-mismatch',
            'domain' => 'first-domain.test',
            'item_id' => 1000,
        ])->assertOk();

        $response = $this->postJson('/api/v1/licenses/verify', [
            'purchase_code' => 'valid-domain-mismatch',
            'domain' => 'second-domain.test',
            'item_id' => 1000,
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error.code', 'DOMAIN_MISMATCH');
    }

    public function test_it_returns_revoked_for_revoked_license(): void
    {
        License::factory()->create([
            'purchase_code' => 'valid-revoked',
            'status' => LicenseStatus::REVOKED,
            'bound_domain' => 'revoked-domain.test',
            'envato_item_id' => 1000,
        ]);

        $response = $this->postJson('/api/v1/licenses/verify', [
            'purchase_code' => 'valid-revoked',
            'domain' => 'revoked-domain.test',
            'item_id' => 1000,
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'LICENSE_REVOKED');
    }

    public function test_it_allows_new_domain_after_admin_reset(): void
    {
        $license = License::factory()->create([
            'purchase_code' => 'valid-reset-domain',
            'bound_domain' => 'old-domain.test',
            'envato_item_id' => 1000,
            'status' => LicenseStatus::ACTIVE,
        ]);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/licenses/{$license->id}/reset-domain", [
            'reason' => 'Customer migration',
        ])->assertOk();

        $response = $this->postJson('/api/v1/licenses/verify', [
            'purchase_code' => 'valid-reset-domain',
            'domain' => 'new-domain.test',
            'item_id' => 1000,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'bound_domain' => 'new-domain.test',
        ]);
    }

    public function test_it_verifies_without_hmac_key_configured(): void
    {
        config()->set('services.license.hmac_key', '');

        $response = $this->postJson('/api/v1/licenses/verify', [
            'purchase_code' => 'valid-no-hmac-key',
            'domain' => 'no-hmac-key.example.com',
            'item_id' => 1000,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.signature', null);
    }
}
