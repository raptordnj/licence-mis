<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEventType;
use App\Enums\LicenseInstanceStatus;
use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\LicenseInstance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminLicenseAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_user_can_view_license_index(): void
    {
        License::factory()->count(2)->create();
        $supportUser = User::factory()->create();

        Sanctum::actingAs($supportUser);

        $response = $this->getJson('/api/v1/admin/licenses');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(2, 'data.data');
    }

    public function test_non_admin_user_cannot_revoke_license(): void
    {
        $license = License::factory()->create();
        $supportUser = User::factory()->create();

        Sanctum::actingAs($supportUser);

        $response = $this->postJson("/api/v1/admin/licenses/{$license->id}/revoke", [
            'reason' => 'Not allowed',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_admin_can_revoke_license_and_write_audit_log(): void
    {
        $license = License::factory()->create([
            'status' => LicenseStatus::ACTIVE,
        ]);

        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/licenses/{$license->id}/revoke", [
            'reason' => 'Fraud report',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'status' => LicenseStatus::REVOKED->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'license_id' => $license->id,
            'actor_id' => $admin->id,
            'event_type' => AuditEventType::LICENSE_REVOKED->value,
        ]);
    }

    public function test_non_admin_user_cannot_reset_license_domain(): void
    {
        $license = License::factory()->create([
            'bound_domain' => 'support-cannot-reset.test',
        ]);

        $supportUser = User::factory()->create();
        Sanctum::actingAs($supportUser);

        $response = $this->postJson("/api/v1/admin/licenses/{$license->id}/reset-domain", [
            'reason' => 'Not allowed',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_admin_can_reset_domain_and_write_audit_log(): void
    {
        $license = License::factory()->create([
            'bound_domain' => 'old-domain.test',
        ]);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/licenses/{$license->id}/reset-domain", [
            'reason' => 'Migration',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'bound_domain' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'license_id' => $license->id,
            'actor_id' => $admin->id,
            'event_type' => AuditEventType::DOMAIN_RESET->value,
        ]);
    }

    public function test_non_admin_user_cannot_reset_license_activations(): void
    {
        $license = License::factory()->create([
            'bound_domain' => 'locked-domain.test',
        ]);

        $supportUser = User::factory()->create();
        Sanctum::actingAs($supportUser);

        $response = $this->postJson("/api/v1/admin/licenses/{$license->id}/reset-activations", [
            'reason' => 'Not allowed',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_admin_can_reset_activations_and_write_audit_log(): void
    {
        $license = License::factory()->create([
            'bound_domain' => 'active-domain.test',
        ]);

        $instance = LicenseInstance::factory()->create([
            'license_id' => $license->id,
            'status' => LicenseInstanceStatus::ACTIVE,
            'domain' => 'active-domain.test',
            'app_url' => 'https://active-domain.test',
        ]);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/admin/licenses/{$license->id}/reset-activations", [
            'reason' => 'Resolve activation lock',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('licenses', [
            'id' => $license->id,
            'bound_domain' => null,
        ]);

        $this->assertDatabaseHas('license_instances', [
            'id' => $instance->id,
            'status' => LicenseInstanceStatus::INACTIVE->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'license_id' => $license->id,
            'actor_id' => $admin->id,
            'event_type' => AuditEventType::DOMAIN_RESET->value,
        ]);
    }
}
