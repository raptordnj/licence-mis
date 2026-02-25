<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AuditEventType;
use App\Models\AuditLog;
use App\Models\License;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'event_type' => AuditEventType::LICENSE_REVOKED,
            'actor_id' => User::factory(),
            'license_id' => License::factory(),
            'metadata' => [],
        ];
    }
}
