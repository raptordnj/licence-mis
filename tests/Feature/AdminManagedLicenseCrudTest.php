<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LicenseStatus;
use App\Enums\Marketplace;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminManagedLicenseCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('license_manager.envato_mock.mode', true);
        config()->set('license_manager.envato_mock.allowed_prefixes', ['VALID-']);
    }

    public function test_it_validates_envato_before_creating_a_managed_license(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $product = Product::factory()->create([
            'envato_item_id' => 1000,
        ]);

        $response = $this->postJson('/api/v1/admin/managed-licenses', [
            'product_id' => $product->id,
            'purchase_code' => ' VALID-AUTO-001 ',
            'notes' => 'Imported from Envato',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.product_id', $product->id);
        $response->assertJsonPath('data.purchase_code', 'VALID-AUTO-001');
        $response->assertJsonPath('data.status', LicenseStatus::VALID->value);
        $response->assertJsonPath('data.metadata.mock.source', 'mock');
        $response->assertJsonPath('data.metadata.mock.matched_by', 'prefix:VALID-');

        $this->assertDatabaseHas('licenses', [
            'product_id' => $product->id,
            'purchase_code' => 'VALID-AUTO-001',
            'status' => LicenseStatus::VALID->value,
            'envato_item_id' => 1000,
            'notes' => 'Imported from Envato',
        ]);
    }

    public function test_it_rejects_creation_when_envato_validation_fails(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $product = Product::factory()->create([
            'envato_item_id' => 1000,
        ]);

        $response = $this->postJson('/api/v1/admin/managed-licenses', [
            'product_id' => $product->id,
            'purchase_code' => 'INVALID-CODE-001',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'PURCHASE_INVALID');

        $this->assertDatabaseMissing('licenses', [
            'purchase_code' => 'INVALID-CODE-001',
        ]);
    }

    public function test_it_rejects_duplicate_purchase_code_after_normalization(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $product = Product::factory()->create([
            'envato_item_id' => 1000,
        ]);

        License::factory()->create([
            'product_id' => $product->id,
            'purchase_code' => 'VALID-DUP-001',
            'marketplace' => Marketplace::ENVATO,
            'envato_item_id' => $product->envato_item_id,
            'status' => LicenseStatus::VALID,
        ]);

        $response = $this->postJson('/api/v1/admin/managed-licenses', [
            'product_id' => $product->id,
            'purchase_code' => '  VALID-DUP-001  ',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $response->assertJsonPath('error.message', 'The purchase code has already been taken.');
    }
}
