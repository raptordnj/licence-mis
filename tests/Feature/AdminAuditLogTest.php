<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEventType;
use App\Models\AuditLog;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_user_can_view_filtered_audit_logs(): void
    {
        $supportUser = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $license = License::factory()->create();

        AuditLog::factory()->create([
            'event_type' => AuditEventType::DOMAIN_RESET,
            'actor_id' => $admin->id,
            'license_id' => $license->id,
        ]);

        AuditLog::factory()->create([
            'event_type' => AuditEventType::LICENSE_REVOKED,
            'actor_id' => $admin->id,
            'license_id' => $license->id,
        ]);

        Sanctum::actingAs($supportUser);

        $response = $this->getJson('/api/v1/admin/audit-logs?event_type=domain_reset');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.event_type', AuditEventType::DOMAIN_RESET->value);
        $response->assertJsonPath('data.data.0.actor.email', $admin->email);
        $response->assertJsonPath('data.data.0.license.purchase_code', $license->purchase_code);
    }

    public function test_audit_log_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(401);
        $response->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}
