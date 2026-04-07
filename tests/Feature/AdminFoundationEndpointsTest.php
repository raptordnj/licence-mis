<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEventType;
use App\Enums\LicenseCheckResult;
use App\Enums\LicenseInstanceStatus;
use App\Enums\LicenseStatus;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\EnvatoItem;
use App\Models\License;
use App\Models\LicenseCheck;
use App\Models\LicenseInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminFoundationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_new_admin_foundation_endpoints(): void
    {
        config()->set('license_manager.envato_mock.mode', true);

        $superAdmin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($superAdmin);

        $envatoItem = EnvatoItem::factory()->create([
            'envato_item_id' => 1001,
        ]);

        $license = License::factory()->create([
            'envato_item_id' => $envatoItem->envato_item_id,
            'status' => LicenseStatus::VALID,
            'metadata' => [
                'buyer' => 'buyer-a',
                'buyer_email' => 'buyer-a@example.com',
                'item_name' => 'Demo Item',
                'domain_requested' => 'example.com',
                'ip' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'correlation_id' => 'corr-1',
                'signature_present' => true,
            ],
        ]);

        AuditLog::factory()->create([
            'event_type' => AuditEventType::DOMAIN_RESET,
            'actor_id' => $superAdmin->id,
            'license_id' => $license->id,
            'metadata' => [
                'domain_requested' => 'example.com',
                'ip' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'item_name' => 'Demo Item',
                'correlation_id' => 'corr-2',
                'signature_present' => true,
            ],
        ]);

        $instance = LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'status' => LicenseInstanceStatus::ACTIVE,
            'domain' => 'example.com',
            'app_url' => 'https://example.com',
        ]);

        LicenseCheck::factory()->create([
            'license_instance_id' => $instance->id,
            'checked_at' => now(),
            'result' => LicenseCheckResult::INVALID,
            'reason' => 'not_found',
            'request_payload' => [
                'purchase_code' => $license->purchase_code,
                'domain' => 'example.com',
                'ip' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
                'correlation_id' => 'corr-license-check-1',
            ],
            'response_payload' => [
                'status' => 'invalid',
                'reason' => 'not_found',
            ],
        ]);

        $itemsResponse = $this->getJson('/api/v1/admin/items?page=1&per_page=10');
        $itemsResponse->assertOk();
        $itemsResponse->assertJsonPath('success', true);
        $itemsResponse->assertJsonPath('data.data.0.envato_item_id', 1001);

        $itemDetailResponse = $this->getJson("/api/v1/admin/items/{$envatoItem->id}");
        $itemDetailResponse->assertOk();
        $itemDetailResponse->assertJsonPath('success', true);
        $itemDetailResponse->assertJsonPath('data.id', $envatoItem->id);
        $itemDetailResponse->assertJsonPath('data.name', $envatoItem->name);

        $createItemResponse = $this->postJson('/api/v1/admin/items', [
            'marketplace' => 'envato',
            'envato_item_id' => 2002,
            'name' => 'Created Item',
            'status' => 'active',
        ]);
        $createItemResponse->assertStatus(201);
        $this->assertDatabaseHas('envato_items', [
            'envato_item_id' => 2002,
            'name' => 'Created Item',
        ]);

        $createdItemId = (int) $createItemResponse->json('data.id');

        $updateItemResponse = $this->putJson("/api/v1/admin/items/{$createdItemId}", [
            'marketplace' => 'envato',
            'envato_item_id' => 2002,
            'name' => 'Updated Item',
            'status' => 'disabled',
        ]);
        $updateItemResponse->assertOk();
        $this->assertDatabaseHas('envato_items', [
            'id' => $createdItemId,
            'status' => 'disabled',
            'name' => 'Updated Item',
        ]);

        $purchasesResponse = $this->getJson('/api/v1/admin/purchases?page=1&per_page=10');
        $purchasesResponse->assertOk();
        $purchasesResponse->assertJsonPath('success', true);
        $this->assertTrue(
            collect($purchasesResponse->json('data.data', []))
                ->contains(static fn (array $entry): bool => data_get($entry, 'purchase_code') === $license->purchase_code),
        );

        $purchaseDetailResponse = $this->getJson("/api/v1/admin/purchases/{$license->id}");
        $purchaseDetailResponse->assertOk();
        $purchaseDetailResponse->assertJsonPath('success', true);
        $purchaseDetailResponse->assertJsonPath('data.purchase_code', $license->purchase_code);
        $purchaseDetailResponse->assertJsonPath('data.instances.0.instance_id', $instance->instance_id);
        $purchaseDetailResponse->assertJsonPath('data.validation_logs.0.reason', 'not_found');
        $purchaseDetailResponse->assertJsonPath('data.audit_trail.0.event', AuditEventType::DOMAIN_RESET->value);

        $validationLogsResponse = $this->getJson('/api/v1/admin/validation-logs?page=1&per_page=10');
        $validationLogsResponse->assertOk();
        $validationLogsResponse->assertJsonPath('success', true);
        $validationLogsResponse->assertJsonPath('data.data.0.result', 'fail');
        $validationLogsResponse->assertJsonPath('data.data.0.fail_reason', 'not_found');
        $validationLogsResponse->assertJsonPath('data.data.0.purchase_code', $license->purchase_code);
        $validationLogsResponse->assertJsonPath('data.data.0.correlation_id', 'corr-license-check-1');

        $usersResponse = $this->getJson('/api/v1/admin/users?page=1&per_page=10');
        $usersResponse->assertOk();
        $usersResponse->assertJsonPath('success', true);

        $createUserResponse = $this->postJson('/api/v1/admin/users', [
            'name' => 'Support User',
            'email' => 'support-created@example.com',
            'role' => RoleName::SUPPORT->value,
            'password' => 'password12345',
        ]);
        $createUserResponse->assertStatus(201);
        $createdUserId = (int) $createUserResponse->json('data.id');

        $updateRoleResponse = $this->patchJson("/api/v1/admin/users/{$createdUserId}/role", [
            'role' => RoleName::ADMIN->value,
        ]);
        $updateRoleResponse->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $createdUserId,
            'role' => RoleName::ADMIN->value,
        ]);

        $tokenTestResponse = $this->getJson('/api/v1/admin/settings/test-envato-token');
        $tokenTestResponse->assertOk();
        $tokenTestResponse->assertJsonPath('success', true);
        $tokenTestResponse->assertJsonPath('data.ok', true);
    }

    public function test_admin_licenses_returns_active_instance_domain_when_bound_domain_is_null(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($superAdmin);

        $envatoItem = EnvatoItem::factory()->create([
            'envato_item_id' => 23014826,
        ]);

        $license = License::factory()->create([
            'envato_item_id' => $envatoItem->envato_item_id,
            'purchase_code' => '41dab85a-a124-4815-8784-0a90cef1f6ab',
            'status' => LicenseStatus::VALID,
            'bound_domain' => null,
        ]);

        $instance = LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'status' => LicenseInstanceStatus::ACTIVE,
            'domain' => 'semsly.envaysoft.com',
            'app_url' => 'https://semsly.envaysoft.com',
            'activated_at' => now()->subMinute(),
        ]);

        LicenseCheck::factory()->create([
            'license_instance_id' => $instance->id,
            'checked_at' => now(),
            'request_payload' => [
                'ip' => '127.0.0.1',
                'user_agent' => 'PHPUnit',
            ],
        ]);

        AuditLog::factory()->create([
            'event_type' => AuditEventType::DOMAIN_RESET,
            'actor_id' => $superAdmin->id,
            'license_id' => $license->id,
            'metadata' => [
                'reason' => 'manual reset',
            ],
        ]);

        $response = $this->getJson('/api/v1/admin/licenses?page=1&per_page=10&search=41dab85a-a124-4815-8784-0a90cef1f6ab');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.data.0.purchase_code', $license->purchase_code);
        $response->assertJsonPath('data.data.0.bound_domain', 'semsly.envaysoft.com');

        $detailResponse = $this->getJson("/api/v1/admin/licenses/{$license->id}");
        $detailResponse->assertOk();
        $detailResponse->assertJsonPath('success', true);
        $detailResponse->assertJsonPath('data.purchase_code', $license->purchase_code);
        $detailResponse->assertJsonPath('data.instances.0.instance_id', $instance->instance_id);
        $detailResponse->assertJsonPath('data.instances.0.domain', 'semsly.envaysoft.com');
        $detailResponse->assertJsonPath('data.validation_logs.0.instance_id', $instance->instance_id);
        $detailResponse->assertJsonPath('data.audit_trail.0.event', AuditEventType::DOMAIN_RESET->value);
    }

    public function test_admin_user_cannot_manage_admin_users_when_not_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $createUserResponse = $this->postJson('/api/v1/admin/users', [
            'name' => 'No Access',
            'email' => 'no-access@example.com',
            'role' => RoleName::SUPPORT->value,
        ]);

        $createUserResponse->assertStatus(403);
        $createUserResponse->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_api_not_found_returns_standardized_envelope(): void
    {
        $response = $this->getJson('/api/v1/admin/does-not-exist');

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('error.code', 'NOT_FOUND');
        $response->assertJsonPath('error.message', 'The requested API endpoint could not be found.');
    }
}
