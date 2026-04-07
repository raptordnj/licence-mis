<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LicenseInstanceStatus;
use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\LicenseInstance;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class PublicLicenseVerificationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKeyPath;

    private string $publicKeyPath;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-02-24 12:30:00');
        [$privateKey, $publicKey] = $this->generateRsaKeyPair();

        $directory = storage_path('framework/testing/keys');

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $this->privateKeyPath = $directory.'/license-private.pem';
        $this->publicKeyPath = $directory.'/license-public.pem';

        file_put_contents($this->privateKeyPath, $privateKey);
        file_put_contents($this->publicKeyPath, $publicKey);

        config()->set('license_manager.jwt.private_key_path', $this->privateKeyPath);
        config()->set('license_manager.jwt.public_key_path', $this->publicKeyPath);
        config()->set('license_manager.jwt.issuer', 'https://licence-mis.local');
        config()->set('license_manager.token_ttl_seconds', 3600);
        config()->set('license_manager.invalid_token_ttl_seconds', 300);
        config()->set('license_manager.verify_rate_limit_per_minute', 120);
        config()->set('license_manager.deactivate_rate_limit_per_minute', 120);
        config()->set('license_manager.envato_mock.mode', true);
        config()->set('license_manager.envato_mock.seed', null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_verify_returns_valid_and_creates_active_instance(): void
    {
        $product = Product::factory()->create([
            'activation_limit' => 2,
            'strict_domain_binding' => true,
        ]);

        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-VALID-001',
            'status' => LicenseStatus::VALID,
            'bound_domain' => null,
        ]);

        $instanceId = (string) Str::uuid();

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => $instanceId,
            'domain' => 'https://www.Example.com/path',
            'app_url' => 'https://www.example.com/install/index.php?foo=bar',
            'app_version' => '4.7.11',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);
        $response->assertJsonPath('domain', 'example.com');
        $response->assertJsonPath('instance_id', $instanceId);
        $response->assertJsonPath('product_id', $product->id);

        $token = (string) $response->json('token');
        $claims = $this->assertJwtAndDecodeClaims($token);

        $this->assertSame('valid', data_get($claims, 'status'));
        $this->assertSame($product->id, data_get($claims, 'product_id'));
        $this->assertSame($instanceId, data_get($claims, 'instance_id'));
        $this->assertSame('example.com', data_get($claims, 'domain'));

        $this->assertDatabaseHas('license_instances', [
            'license_id' => $license->id,
            'instance_id' => $instanceId,
            'domain' => 'example.com',
            'status' => LicenseInstanceStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'bound_domain' => 'example.com',
        ]);

        $this->assertDatabaseHas('license_checks', [
            'result' => 'valid',
            'reason' => null,
        ]);
    }

    public function test_verify_accepts_sha512_purchase_code_when_local_license_exists(): void
    {
        $product = Product::factory()->create([
            'activation_limit' => 2,
            'strict_domain_binding' => true,
        ]);

        $rawPurchaseCode = 'PCODE-HASHED-VERIFY-001';
        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => $rawPurchaseCode,
            'status' => LicenseStatus::VALID,
            'bound_domain' => null,
        ]);

        $instanceId = (string) Str::uuid();
        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => hash('sha512', $rawPurchaseCode),
            'product_id' => $product->id,
            'instance_id' => $instanceId,
            'domain' => 'https://hashed.example.com/path',
            'app_url' => 'https://hashed.example.com/app',
            'app_version' => '5.0.0',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);
        $response->assertJsonPath('instance_id', $instanceId);
        $response->assertJsonPath('domain', 'hashed.example.com');

        $this->assertDatabaseHas('license_instances', [
            'license_id' => $license->id,
            'instance_id' => $instanceId,
            'domain' => 'hashed.example.com',
            'status' => LicenseInstanceStatus::ACTIVE->value,
        ]);
    }

    public function test_verify_returns_not_found_when_purchase_code_is_unknown(): void
    {
        $product = Product::factory()->create();
        $instanceId = (string) Str::uuid();

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'NOT-FOUND-CODE',
            'product_id' => $product->id,
            'instance_id' => $instanceId,
            'domain' => 'missing.example.com',
            'app_url' => 'https://missing.example.com',
            'app_version' => '1.0.0',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'invalid');
        $response->assertJsonPath('reason', 'not_found');
        $response->assertJsonPath('instance_id', $instanceId);

        $this->assertDatabaseHas('license_checks', [
            'result' => 'invalid',
            'reason' => 'not_found',
            'license_instance_id' => null,
        ]);
    }

    public function test_verify_falls_back_to_envato_item_id_when_product_id_does_not_exist(): void
    {
        $product = Product::factory()->create([
            'envato_item_id' => 23014826,
        ]);

        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-FALLBACK-ITEM-001',
            'status' => LicenseStatus::VALID,
        ]);

        $instanceId = (string) Str::uuid();

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => $license->purchase_code,
            'product_id' => 999999,
            'envato_item_id' => $product->envato_item_id,
            'instance_id' => $instanceId,
            'domain' => 'fallback-item.example.com',
            'app_url' => 'https://fallback-item.example.com',
            'app_version' => '1.0.1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);
        $response->assertJsonPath('product_id', $product->id);
    }

    public function test_verify_prefers_envato_item_id_when_product_id_points_to_different_product(): void
    {
        $wrongProduct = Product::factory()->create([
            'envato_item_id' => 999001,
        ]);

        $correctProduct = Product::factory()->create([
            'envato_item_id' => 23014826,
        ]);

        $license = License::factory()->forProduct($correctProduct)->create([
            'purchase_code' => 'PCODE-FALLBACK-MISMATCHED-PRODUCT-001',
            'status' => LicenseStatus::VALID,
        ]);

        $instanceId = (string) Str::uuid();

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $wrongProduct->id,
            'envato_item_id' => $correctProduct->envato_item_id,
            'instance_id' => $instanceId,
            'domain' => 'fallback-mismatch.example.com',
            'app_url' => 'https://fallback-mismatch.example.com',
            'app_version' => '1.0.2',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);
        $response->assertJsonPath('product_id', $correctProduct->id);
    }

    public function test_verify_returns_revoked_when_license_is_revoked(): void
    {
        $product = Product::factory()->create();
        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-REVOKED-001',
            'status' => LicenseStatus::REVOKED,
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'revoked.example.com',
            'app_url' => 'https://revoked.example.com',
            'app_version' => '2.0.0',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'invalid');
        $response->assertJsonPath('reason', 'revoked');
    }

    public function test_verify_returns_refund_when_license_is_refunded(): void
    {
        $product = Product::factory()->create();
        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-REFUND-001',
            'status' => LicenseStatus::REFUNDED,
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'refunded.example.com',
            'app_url' => 'https://refunded.example.com',
            'app_version' => '2.0.0',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'invalid');
        $response->assertJsonPath('reason', 'refund');
    }

    public function test_verify_returns_refund_when_license_is_chargeback(): void
    {
        $product = Product::factory()->create();
        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-CHARGEBACK-001',
            'status' => LicenseStatus::CHARGEBACK,
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'chargeback.example.com',
            'app_url' => 'https://chargeback.example.com',
            'app_version' => '2.0.0',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'invalid');
        $response->assertJsonPath('reason', 'refund');
    }

    public function test_verify_returns_limit_reached_for_new_instance_after_limit(): void
    {
        $product = Product::factory()->create([
            'activation_limit' => 1,
            'strict_domain_binding' => false,
        ]);

        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-LIMIT-001',
            'status' => LicenseStatus::VALID,
        ]);

        LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'instance_id' => (string) Str::uuid(),
            'status' => LicenseInstanceStatus::ACTIVE,
            'domain' => 'first.example.com',
            'app_url' => 'https://first.example.com',
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'second.example.com',
            'app_url' => 'https://second.example.com',
            'app_version' => '3.0.0',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'invalid');
        $response->assertJsonPath('reason', 'limit_reached');
    }

    public function test_verify_returns_domain_mismatch_when_strict_binding_is_enabled(): void
    {
        $product = Product::factory()->create([
            'activation_limit' => 3,
            'strict_domain_binding' => true,
        ]);

        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-DOMAIN-001',
            'status' => LicenseStatus::VALID,
        ]);

        LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'instance_id' => (string) Str::uuid(),
            'status' => LicenseInstanceStatus::ACTIVE,
            'domain' => 'bound.example.com',
            'app_url' => 'https://bound.example.com',
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'other.example.com',
            'app_url' => 'https://other.example.com',
            'app_version' => '3.0.0',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'invalid');
        $response->assertJsonPath('reason', 'domain_mismatch');
    }

    public function test_verify_allows_new_domain_after_admin_reset_domain_deactivates_old_instances(): void
    {
        $product = Product::factory()->create([
            'activation_limit' => 3,
            'strict_domain_binding' => true,
        ]);

        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-RESET-DOMAIN-001',
            'status' => LicenseStatus::VALID,
            'bound_domain' => 'old.example.com',
        ]);

        $oldInstance = LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'instance_id' => (string) Str::uuid(),
            'status' => LicenseInstanceStatus::ACTIVE,
            'domain' => 'old.example.com',
            'app_url' => 'https://old.example.com',
        ]);

        $beforeResetResponse = $this->postJson('/api/licenses/verify', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'new.example.com',
            'app_url' => 'https://new.example.com',
            'app_version' => '3.1.0',
        ]);

        $beforeResetResponse->assertOk();
        $beforeResetResponse->assertJsonPath('status', 'invalid');
        $beforeResetResponse->assertJsonPath('reason', 'domain_mismatch');

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/admin/licenses/{$license->id}/reset-domain", [
            'reason' => 'Customer migration',
        ])->assertOk();

        $oldInstance->refresh();
        $this->assertSame(LicenseInstanceStatus::INACTIVE, $oldInstance->status);
        $this->assertNotNull($oldInstance->deactivated_at);

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'bound_domain' => null,
        ]);

        $afterResetResponse = $this->postJson('/api/licenses/verify', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'new.example.com',
            'app_url' => 'https://new.example.com',
            'app_version' => '3.1.0',
        ]);

        $afterResetResponse->assertOk();
        $afterResetResponse->assertJsonPath('status', 'valid');
        $afterResetResponse->assertJsonPath('reason', null);
        $afterResetResponse->assertJsonPath('domain', 'new.example.com');

        $this->assertDatabaseHas('license_instances', [
            'license_id' => $license->id,
            'domain' => 'new.example.com',
            'status' => LicenseInstanceStatus::ACTIVE->value,
        ]);
    }

    public function test_verify_reuses_existing_active_instance(): void
    {
        $product = Product::factory()->create([
            'activation_limit' => 2,
            'strict_domain_binding' => true,
        ]);

        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-REUSE-001',
            'status' => LicenseStatus::VALID,
        ]);

        $instance = LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'instance_id' => (string) Str::uuid(),
            'status' => LicenseInstanceStatus::ACTIVE,
            'domain' => 'reuse.example.com',
            'app_url' => 'https://reuse.example.com',
            'last_seen_at' => now()->subDay(),
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => $instance->instance_id,
            'domain' => 'reuse.example.com',
            'app_url' => 'https://reuse.example.com/install',
            'app_version' => '3.0.1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);

        $instance->refresh();
        $this->assertSame(now()->toDateTimeString(), $instance->last_seen_at?->toDateTimeString());
    }

    public function test_verify_returns_bad_request_for_invalid_payload_and_still_signs(): void
    {
        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'INVALID-PAYLOAD',
            'product_id' => 1,
            'instance_id' => 'not-a-uuid',
            'domain' => '',
            'app_url' => 'this-is-not-a-url',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'invalid');
        $response->assertJsonPath('reason', 'bad_request');
        $this->assertNotSame('', (string) $response->json('token'));

        $this->assertDatabaseHas('license_checks', [
            'result' => 'invalid',
            'reason' => 'bad_request',
        ]);
    }

    public function test_deactivate_sets_instance_inactive_and_returns_signed_response(): void
    {
        $product = Product::factory()->create();
        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-DEACT-001',
            'status' => LicenseStatus::VALID,
            'bound_domain' => 'deactivated.example.com',
        ]);

        $instance = LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'status' => LicenseInstanceStatus::ACTIVE,
            'domain' => 'deactivated.example.com',
            'app_url' => 'https://deactivated.example.com',
        ]);

        $response = $this->postJson('/api/licenses/deactivate', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => $instance->instance_id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('reason', 'deactivated');
        $token = (string) $response->json('token');
        $this->assertNotSame('', $token);
        $claims = $this->assertJwtAndDecodeClaims($token);
        $this->assertSame('valid', data_get($claims, 'status'));
        $this->assertSame(true, data_get($claims, 'success'));
        $this->assertSame('deactivated', data_get($claims, 'reason'));
        $this->assertSame($instance->instance_id, data_get($claims, 'instance_id'));
        $this->assertSame('deactivated.example.com', data_get($claims, 'domain'));

        $instance->refresh();
        $this->assertSame(LicenseInstanceStatus::INACTIVE, $instance->status);
        $this->assertNotNull($instance->deactivated_at);

        $this->assertDatabaseHas('license_checks', [
            'license_instance_id' => $instance->id,
            'result' => 'valid',
            'reason' => 'deactivated',
        ]);

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'bound_domain' => null,
        ]);
    }

    public function test_deactivate_accepts_sha512_purchase_code_when_local_license_exists(): void
    {
        $product = Product::factory()->create();
        $rawPurchaseCode = 'PCODE-HASHED-DEACT-001';
        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => $rawPurchaseCode,
            'status' => LicenseStatus::VALID,
            'bound_domain' => 'hashed-deactivated.example.com',
        ]);

        $instance = LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'status' => LicenseInstanceStatus::ACTIVE,
            'domain' => 'hashed-deactivated.example.com',
            'app_url' => 'https://hashed-deactivated.example.com',
        ]);

        $response = $this->postJson('/api/licenses/deactivate', [
            'purchase_code' => hash('sha512', $rawPurchaseCode),
            'product_id' => $product->id,
            'instance_id' => $instance->instance_id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('reason', 'deactivated');

        $instance->refresh();
        $this->assertSame(LicenseInstanceStatus::INACTIVE, $instance->status);
        $this->assertNotNull($instance->deactivated_at);
    }

    public function test_deactivate_returns_not_found_when_instance_is_missing(): void
    {
        $product = Product::factory()->create();
        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-DEACT-NOT-FOUND',
            'status' => LicenseStatus::VALID,
        ]);

        $response = $this->postJson('/api/licenses/deactivate', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('reason', 'not_found');
        $token = (string) $response->json('token');
        $this->assertNotSame('', $token);
        $claims = $this->assertJwtAndDecodeClaims($token);
        $this->assertSame('invalid', data_get($claims, 'status'));
        $this->assertSame(false, data_get($claims, 'success'));
        $this->assertSame('not_found', data_get($claims, 'reason'));
        $this->assertSame('', data_get($claims, 'domain'));
    }

    public function test_deactivate_is_idempotent_for_inactive_instance(): void
    {
        $product = Product::factory()->create();
        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-DEACT-IDEMPOTENT',
            'status' => LicenseStatus::VALID,
        ]);

        $instance = LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'status' => LicenseInstanceStatus::INACTIVE,
            'domain' => 'idempotent.example.com',
            'app_url' => 'https://idempotent.example.com',
            'deactivated_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/licenses/deactivate', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => $instance->instance_id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('reason', 'deactivated');

        $claims = $this->assertJwtAndDecodeClaims((string) $response->json('token'));
        $this->assertSame('valid', data_get($claims, 'status'));
        $this->assertSame(true, data_get($claims, 'success'));
        $this->assertSame('deactivated', data_get($claims, 'reason'));
        $this->assertSame('idempotent.example.com', data_get($claims, 'domain'));
    }

    public function test_deactivate_uses_app_url_host_when_instance_domain_is_empty(): void
    {
        $product = Product::factory()->create();
        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-DEACT-DOMAIN-FALLBACK',
            'status' => LicenseStatus::VALID,
        ]);

        $instance = LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'status' => LicenseInstanceStatus::ACTIVE,
            'domain' => '',
            'app_url' => 'https://fallback.example.com/install/path',
        ]);

        $response = $this->postJson('/api/licenses/deactivate', [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => $instance->instance_id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('reason', 'deactivated');

        $claims = $this->assertJwtAndDecodeClaims((string) $response->json('token'));
        $this->assertSame('fallback.example.com', data_get($claims, 'domain'));
    }

    public function test_verify_endpoint_is_rate_limited_per_ip_and_purchase_code(): void
    {
        config()->set('license_manager.verify_rate_limit_per_minute', 1);

        $product = Product::factory()->create();
        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'PCODE-RATE-LIMIT-001',
            'status' => LicenseStatus::VALID,
        ]);

        $payload = [
            'purchase_code' => $license->purchase_code,
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'ratelimit.example.com',
            'app_url' => 'https://ratelimit.example.com',
            'app_version' => '1.0.0',
        ];

        $this->postJson('/api/licenses/verify', $payload)->assertOk();
        $this->postJson('/api/licenses/verify', $payload)->assertStatus(429);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function generateRsaKeyPair(): array
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);

        if ($resource === false) {
            throw new RuntimeException('Failed to generate RSA key pair.');
        }

        $privateKey = '';
        openssl_pkey_export($resource, $privateKey);

        $details = openssl_pkey_get_details($resource);

        if (! is_array($details) || ! isset($details['key']) || ! is_string($details['key'])) {
            throw new RuntimeException('Failed to export public key.');
        }

        return [$privateKey, $details['key']];
    }

    /**
     * @return array<string, mixed>
     */
    private function assertJwtAndDecodeClaims(string $token): array
    {
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $signingInput = "{$encodedHeader}.{$encodedPayload}";
        $signature = $this->base64UrlDecode($encodedSignature);
        $publicKey = openssl_pkey_get_public((string) file_get_contents($this->publicKeyPath));

        $this->assertNotFalse($publicKey);
        $this->assertSame(1, openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256));

        $payload = json_decode($this->base64UrlDecode($encodedPayload), true);
        $this->assertIsArray($payload);

        /** @var array<string, mixed> $claims */
        $claims = $payload;

        return $claims;
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = 4 - (strlen($value) % 4);

        if ($padding < 4) {
            $value .= str_repeat('=', $padding);
        }

        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
