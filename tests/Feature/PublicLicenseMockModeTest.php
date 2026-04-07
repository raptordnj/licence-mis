<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LicenseInstanceStatus;
use App\Enums\LicenseStatus;
use App\Services\Contracts\EnvatoPurchaseValidatorInterface;
use App\Services\Contracts\SensitiveSettingsStoreInterface;
use App\Services\EnvatoApiPurchaseValidator;
use App\Services\MockPurchaseValidator;
use App\Models\License;
use App\Models\LicenseInstance;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class PublicLicenseMockModeTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKeyPath;

    private string $publicKeyPath;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-02-24 15:00:00');
        [$privateKey, $publicKey] = $this->generateRsaKeyPair();

        $keyDirectory = storage_path('framework/testing/keys');
        $fixtureDirectory = storage_path('framework/testing/envato-mock');

        if (! is_dir($keyDirectory)) {
            mkdir($keyDirectory, 0777, true);
        }

        if (! is_dir($fixtureDirectory)) {
            mkdir($fixtureDirectory, 0777, true);
        }

        $this->privateKeyPath = $keyDirectory.'/mock-mode-private.pem';
        $this->publicKeyPath = $keyDirectory.'/mock-mode-public.pem';
        $this->fixturePath = $fixtureDirectory.'/fixtures-feature.json';

        file_put_contents($this->privateKeyPath, $privateKey);
        file_put_contents($this->publicKeyPath, $publicKey);
        file_put_contents($this->fixturePath, json_encode(['fixtures' => []], JSON_THROW_ON_ERROR));

        config()->set('license_manager.jwt.private_key_path', $this->privateKeyPath);
        config()->set('license_manager.jwt.public_key_path', $this->publicKeyPath);
        config()->set('license_manager.jwt.issuer', 'https://licence-mis.local');
        config()->set('license_manager.token_ttl_seconds', 3600);
        config()->set('license_manager.invalid_token_ttl_seconds', 300);
        config()->set('license_manager.verify_rate_limit_per_minute', 120);
        config()->set('license_manager.deactivate_rate_limit_per_minute', 120);
        config()->set('license_manager.envato_mock.mode', true);
        config()->set('license_manager.envato_mock.allowed_prefixes', ['MOCK-', 'TEST-']);
        config()->set('license_manager.envato_mock.seed', null);
        config()->set('license_manager.envato_mock.fixture_path', $this->fixturePath);
        config()->set('services.envato.token', 'feature-test-token');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::delete($this->fixturePath);

        parent::tearDown();
    }

    public function test_verify_returns_signed_jwt_for_mock_prefix_code_without_envato_http_calls(): void
    {
        Http::fake();

        $product = Product::factory()->create([
            'envato_item_id' => 47001,
        ]);

        $instanceId = (string) Str::uuid();

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'MOCK-VALID-001',
            'product_id' => $product->id,
            'instance_id' => $instanceId,
            'domain' => 'https://www.example.com/install',
            'app_url' => 'https://www.example.com/install/index.php',
            'app_version' => '5.0.0',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);
        $response->assertJsonPath('instance_id', $instanceId);
        $response->assertJsonPath('domain', 'example.com');
        $this->assertNotSame('', (string) $response->json('token'));

        $claims = $this->assertJwtAndDecodeClaims((string) $response->json('token'));
        $this->assertSame('valid', data_get($claims, 'status'));
        $this->assertSame($instanceId, data_get($claims, 'instance_id'));

        $this->assertDatabaseHas('licenses', [
            'purchase_code' => 'MOCK-VALID-001',
            'product_id' => $product->id,
        ]);

        Http::assertNothingSent();
    }

    public function test_verify_returns_fixture_reason_for_invalid_mock_purchase_code(): void
    {
        $this->writeFixtures([
            [
                'purchase_code' => 'FIXTURE-REFUND-001',
                'valid' => false,
                'reason' => 'refund',
            ],
        ]);

        Http::fake();

        $product = Product::factory()->create([
            'envato_item_id' => 47002,
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'FIXTURE-REFUND-001',
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'fixture.example.com',
            'app_url' => 'https://fixture.example.com',
            'app_version' => '5.0.1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'invalid');
        $response->assertJsonPath('reason', 'refund');
        $this->assertNotSame('', (string) $response->json('token'));

        Http::assertNothingSent();
    }

    public function test_verify_calls_envato_api_when_mock_mode_is_off(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $product = Product::factory()->create([
            'envato_item_id' => 47003,
        ]);

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'matches' => [
                    [
                        'buyer' => 'real-api-buyer',
                        'supported_until' => '2030-12-31T00:00:00+00:00',
                        'item' => [
                            'id' => $product->envato_item_id,
                            'name' => 'Real API Item',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'REAL-API-VALID-001',
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'real.example.com',
            'app_url' => 'https://real.example.com',
            'app_version' => '5.0.2',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);

        Http::assertSent(static function (HttpRequest $request): bool {
            return str_contains($request->url(), 'api.envato.com')
                && data_get($request->data(), 'code') === 'REAL-API-VALID-001';
        });

        $this->assertDatabaseHas('licenses', [
            'purchase_code' => 'REAL-API-VALID-001',
            'product_id' => $product->id,
        ]);
    }

    public function test_verify_falls_back_to_buyer_endpoint_when_author_sale_returns_not_found(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $product = Product::factory()->create([
            'envato_item_id' => 47008,
        ]);

        Http::fake([
            'https://api.envato.com/*/author/sale*' => Http::response([
                'error' => 'No sale found for this code.',
            ], 404),
            'https://api.envato.com/*/buyer/purchase*' => Http::response([
                'matches' => [
                    [
                        'buyer' => 'buyer-scope-user',
                        'supported_until' => '2033-12-31T00:00:00+00:00',
                        'item' => [
                            'id' => $product->envato_item_id,
                            'name' => 'Buyer Scope Item',
                        ],
                    ],
                ],
            ], 200),
            'https://api.envato.com/*' => Http::response([], 500),
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'REAL-API-BUYER-FALLBACK-001',
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'buyer-fallback.example.com',
            'app_url' => 'https://buyer-fallback.example.com',
            'app_version' => '5.0.8',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);

        Http::assertSent(static fn (HttpRequest $request): bool => str_contains($request->url(), '/author/sale'));
        Http::assertSent(static fn (HttpRequest $request): bool => str_contains($request->url(), '/buyer/purchase'));

        $this->assertDatabaseHas('licenses', [
            'purchase_code' => 'REAL-API-BUYER-FALLBACK-001',
            'product_id' => $product->id,
        ]);
    }

    public function test_verify_uses_local_database_after_first_successful_envato_verification(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $product = Product::factory()->create([
            'envato_item_id' => 47006,
        ]);

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'matches' => [
                    [
                        'buyer' => 'cached-local-buyer',
                        'supported_until' => '2032-12-31T00:00:00+00:00',
                        'item' => [
                            'id' => $product->envato_item_id,
                            'name' => 'Cached Local Item',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $instanceId = (string) Str::uuid();
        $payload = [
            'purchase_code' => 'REAL-API-LOCAL-001',
            'product_id' => $product->id,
            'instance_id' => $instanceId,
            'domain' => 'cached.example.com',
            'app_url' => 'https://cached.example.com',
            'app_version' => '5.0.6',
        ];

        $this->postJson('/api/licenses/verify', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'valid');

        $this->postJson('/api/licenses/verify', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'valid');

        Http::assertSentCount(1);
    }

    public function test_verify_checks_existing_domain_locally_before_envato_lookup(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $product = Product::factory()->create([
            'envato_item_id' => 47007,
            'strict_domain_binding' => true,
        ]);

        $license = License::factory()->forProduct($product)->create([
            'purchase_code' => 'LOCAL-DOMAIN-CODE-001',
            'status' => LicenseStatus::VALID,
            'bound_domain' => 'bound-local.example.com',
        ]);

        LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'status' => LicenseInstanceStatus::ACTIVE,
            'domain' => 'bound-local.example.com',
            'app_url' => 'https://bound-local.example.com',
        ]);

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'matches' => [
                    [
                        'buyer' => 'should-not-call',
                        'supported_until' => '2032-12-31T00:00:00+00:00',
                        'item' => [
                            'id' => $product->envato_item_id,
                            'name' => 'Should Not Call Item',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'DIFFERENT-CODE-999',
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'bound-local.example.com',
            'app_url' => 'https://bound-local.example.com/install',
            'app_version' => '5.0.7',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'invalid');
        $response->assertJsonPath('reason', 'domain_mismatch');

        Http::assertNothingSent();
    }

    public function test_verify_uses_existing_local_purchase_without_calling_envato(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $product = Product::factory()->create([
            'envato_item_id' => 47009,
        ]);

        $license = License::factory()->create([
            'product_id' => null,
            'purchase_code' => 'EXISTING-LOCAL-CODE-001',
            'envato_item_id' => $product->envato_item_id,
            'status' => LicenseStatus::VALID,
            'bound_domain' => null,
        ]);

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'matches' => [
                    [
                        'buyer' => 'should-not-be-called',
                        'supported_until' => '2034-12-31T00:00:00+00:00',
                        'item' => [
                            'id' => $product->envato_item_id,
                            'name' => 'Should Not Be Called',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => '  EXISTING-LOCAL-CODE-001  ',
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'existing-local.example.com',
            'app_url' => 'https://existing-local.example.com',
            'app_version' => '5.1.0',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);
        $response->assertJsonPath('product_id', $product->id);

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'purchase_code' => 'EXISTING-LOCAL-CODE-001',
            'product_id' => $product->id,
        ]);

        Http::assertNothingSent();
    }

    public function test_verify_uses_local_license_product_when_request_product_is_wrong(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $correctProduct = Product::factory()->create([
            'envato_item_id' => 47020,
        ]);

        $wrongProduct = Product::factory()->create([
            'envato_item_id' => 47021,
        ]);

        License::factory()->forProduct($correctProduct)->create([
            'purchase_code' => 'LOCAL-WRONG-PRODUCT-001',
            'status' => LicenseStatus::VALID,
            'bound_domain' => null,
        ]);

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'matches' => [
                    [
                        'buyer' => 'should-not-call',
                        'supported_until' => '2034-12-31T00:00:00+00:00',
                        'item' => [
                            'id' => $correctProduct->envato_item_id,
                            'name' => 'Should Not Call',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'LOCAL-WRONG-PRODUCT-001',
            'product_id' => $wrongProduct->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'wrong-product.example.com',
            'app_url' => 'https://wrong-product.example.com',
            'app_version' => '5.1.05',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);
        $response->assertJsonPath('product_id', $correctProduct->id);

        Http::assertNothingSent();
    }

    public function test_verify_resolves_correct_product_from_envato_when_requested_product_is_wrong(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $wrongProduct = Product::factory()->create([
            'envato_item_id' => 47030,
        ]);

        $correctProduct = Product::factory()->create([
            'envato_item_id' => 47031,
        ]);

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'matches' => [
                    [
                        'buyer' => 'resolved-from-envato',
                        'supported_until' => '2036-12-31T00:00:00+00:00',
                        'item' => [
                            'id' => $correctProduct->envato_item_id,
                            'name' => 'Resolved Product Item',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'ENVATO-RESOLVE-PRODUCT-001',
            'product_id' => $wrongProduct->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'resolve-product.example.com',
            'app_url' => 'https://resolve-product.example.com',
            'app_version' => '5.1.06',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);
        $response->assertJsonPath('product_id', $correctProduct->id);

        $this->assertDatabaseHas('licenses', [
            'purchase_code' => 'ENVATO-RESOLVE-PRODUCT-001',
            'product_id' => $correctProduct->id,
            'envato_item_id' => $correctProduct->envato_item_id,
        ]);
    }

    public function test_verify_falls_back_to_requested_product_when_envato_item_has_no_local_mapping(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $requestedProduct = Product::factory()->create([
            'envato_item_id' => 47040,
        ]);

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'matches' => [
                    [
                        'buyer' => 'fallback-requested-product',
                        'supported_until' => '2037-12-31T00:00:00+00:00',
                        'item' => [
                            'id' => 47999,
                            'name' => 'Unmapped Envato Item',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'ENVATO-FALLBACK-REQUESTED-001',
            'product_id' => $requestedProduct->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'fallback-requested.example.com',
            'app_url' => 'https://fallback-requested.example.com',
            'app_version' => '5.1.07',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);
        $response->assertJsonPath('product_id', $requestedProduct->id);

        $this->assertDatabaseHas('licenses', [
            'purchase_code' => 'ENVATO-FALLBACK-REQUESTED-001',
            'product_id' => $requestedProduct->id,
            'envato_item_id' => 47999,
        ]);
    }

    public function test_verify_saves_license_when_envato_valid_and_local_missing(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $product = Product::factory()->create([
            'envato_item_id' => 47010,
        ]);

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'matches' => [
                    [
                        'buyer' => 'envato-save-buyer',
                        'supported_until' => '2035-12-31T00:00:00+00:00',
                        'item' => [
                            'id' => $product->envato_item_id,
                            'name' => 'Envato Persisted Item',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => '  NEW-ENVATO-VALID-001  ',
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'envato-save.example.com',
            'app_url' => 'https://envato-save.example.com',
            'app_version' => '5.1.1',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'valid');
        $response->assertJsonPath('reason', null);

        $this->assertDatabaseHas('licenses', [
            'purchase_code' => 'NEW-ENVATO-VALID-001',
            'product_id' => $product->id,
            'buyer' => 'envato-save-buyer',
            'envato_item_id' => $product->envato_item_id,
            'status' => LicenseStatus::VALID->value,
        ]);
    }

    public function test_verify_returns_not_found_when_envato_reports_missing_purchase(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $product = Product::factory()->create([
            'envato_item_id' => 47011,
        ]);

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'error' => 'No purchase found for the given code.',
            ], 404),
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'NEW-ENVATO-NOT-FOUND-001',
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'envato-missing.example.com',
            'app_url' => 'https://envato-missing.example.com',
            'app_version' => '5.1.2',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'invalid');
        $response->assertJsonPath('reason', 'not_found');

        $this->assertDatabaseMissing('licenses', [
            'purchase_code' => 'NEW-ENVATO-NOT-FOUND-001',
            'product_id' => $product->id,
        ]);
    }

    public function test_verify_returns_envato_unavailable_envelope_when_provider_fails(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $product = Product::factory()->create([
            'envato_item_id' => 47012,
        ]);

        Http::fake([
            'https://api.envato.com/*' => Http::response([], 500),
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'NEW-ENVATO-UNAVAILABLE-001',
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'envato-unavailable.example.com',
            'app_url' => 'https://envato-unavailable.example.com',
            'app_version' => '5.1.3',
        ]);

        $response->assertStatus(503);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'ENVATO_UNAVAILABLE');
        $response->assertJsonPath('error.message', 'Verification provider is currently unavailable.');
    }

    public function test_admin_setting_can_toggle_mock_mode_runtime_behavior(): void
    {
        config()->set('license_manager.envato_mock.mode', false);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings', [
            'envato_mock_mode' => true,
        ])->assertOk();

        $this->assertTrue($this->app->make(SensitiveSettingsStoreInterface::class)->isEnvatoMockModeEnabled());
        $this->assertInstanceOf(
            MockPurchaseValidator::class,
            $this->app->make(EnvatoPurchaseValidatorInterface::class),
        );

        Http::fake();

        $product = Product::factory()->create([
            'envato_item_id' => 47004,
        ]);

        $mockResponse = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'MOCK-TOGGLE-001',
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'toggle.example.com',
            'app_url' => 'https://toggle.example.com',
            'app_version' => '5.0.3',
        ]);

        $mockResponse->assertOk();
        $mockResponse->assertJsonPath('status', 'valid');
        Http::assertNothingSent();

        $this->putJson('/api/v1/admin/settings', [
            'envato_mock_mode' => false,
        ])->assertOk();

        $this->assertFalse($this->app->make(SensitiveSettingsStoreInterface::class)->isEnvatoMockModeEnabled());
        $this->assertInstanceOf(
            EnvatoApiPurchaseValidator::class,
            $this->app->make(EnvatoPurchaseValidatorInterface::class),
        );

        Http::fake([
            'https://api.envato.com/*' => Http::response([
                'matches' => [
                    [
                        'buyer' => 'toggle-api-buyer',
                        'supported_until' => '2031-12-31T00:00:00+00:00',
                        'item' => [
                            'id' => $product->envato_item_id,
                            'name' => 'Toggle API Item',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $apiResponse = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'REAL-TOGGLE-001',
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'toggle2.example.com',
            'app_url' => 'https://toggle2.example.com',
            'app_version' => '5.0.4',
        ]);

        $apiResponse->assertOk();
        Http::assertSent(static fn (HttpRequest $request): bool => str_contains($request->url(), 'api.envato.com'));
    }

    public function test_production_fail_closed_blocks_verify_when_mock_mode_enabled_from_admin_settings(): void
    {
        config()->set('app.env', 'production');
        config()->set('license_manager.envato_mock.mode', false);
        config()->set('license_manager.envato_mock.fail_closed_in_prod', true);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/settings', [
            'envato_mock_mode' => true,
        ])->assertOk();

        Http::fake();

        $product = Product::factory()->create([
            'envato_item_id' => 47005,
        ]);

        $response = $this->postJson('/api/licenses/verify', [
            'purchase_code' => 'MOCK-BLOCKED-001',
            'product_id' => $product->id,
            'instance_id' => (string) Str::uuid(),
            'domain' => 'production-guard.example.com',
            'app_url' => 'https://production-guard.example.com',
            'app_version' => '5.0.5',
        ]);

        $response->assertStatus(500);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'INTERNAL_ERROR');

        Http::assertNothingSent();
    }

    /**
     * @param  array<int, array<string, mixed>>  $fixtures
     */
    private function writeFixtures(array $fixtures): void
    {
        file_put_contents(
            $this->fixturePath,
            json_encode(['fixtures' => $fixtures], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
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
