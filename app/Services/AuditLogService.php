<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditEventType;
use App\Models\AuditLog;
use App\Models\User;

readonly class AuditLogService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        AuditEventType $eventType,
        ?User $actor,
        ?int $licenseId,
        array $metadata = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'event_type' => $eventType,
            'actor_id' => $actor?->id,
            'license_id' => $licenseId,
            'metadata' => $metadata,
        ]);
    }
}
