<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEventType;
use App\Enums\LicenseStatus;
use App\Models\AuditLog;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_fetch_dashboard_payload(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('admin-api')->plainTextToken;

        License::factory()->count(2)->create([
            'status' => LicenseStatus::ACTIVE,
        ]);
        License::factory()->create([
            'status' => LicenseStatus::REVOKED,
        ]);
        $expired = License::factory()->create([
            'status' => LicenseStatus::EXPIRED,
            'purchase_code' => 'expired-license',
        ]);

        AuditLog::factory()->create([
            'event_type' => AuditEventType::LICENSE_REVOKED,
            'actor_id' => $admin->id,
            'license_id' => $expired->id,
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.metrics.total_licenses', 4);
        $response->assertJsonPath('data.metrics.active_licenses', 2);
        $response->assertJsonPath('data.metrics.revoked_licenses', 1);
        $response->assertJsonPath('data.metrics.expired_licenses', 1);
        $response->assertJsonCount(4, 'data.recent_licenses');
        $response->assertJsonPath('data.recent_licenses.0.purchase_code', 'expired-license');
        $response->assertJsonPath('data.recent_audit_logs.0.event_type', AuditEventType::LICENSE_REVOKED->value);
        $response->assertJsonPath('data.recent_audit_logs.0.actor.email', $admin->email);
        $response->assertJsonPath('data.recent_audit_logs.0.license.purchase_code', 'expired-license');
    }

    public function test_non_admin_user_cannot_access_dashboard_payload(): void
    {
        $supportUser = User::factory()->create();
        $token = $supportUser->createToken('support-api')->plainTextToken;

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
