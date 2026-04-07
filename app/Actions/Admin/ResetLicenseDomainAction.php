<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\AuditEventType;
use App\Enums\LicenseInstanceStatus;
use App\Models\License;
use App\Models\User;
use App\Services\AuditLogService;

readonly class ResetLicenseDomainAction
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function execute(License $license, User $actor, ?string $reason = null): License
    {
        $deactivatedInstances = $license->instances()
            ->where('status', LicenseInstanceStatus::ACTIVE->value)
            ->update([
                'status' => LicenseInstanceStatus::INACTIVE->value,
                'deactivated_at' => now(),
                'last_seen_at' => now(),
            ]);

        $license->bound_domain = null;
        $license->save();

        $this->auditLogService->log(
            eventType: AuditEventType::DOMAIN_RESET,
            actor: $actor,
            licenseId: $license->id,
            metadata: [
                'reason' => $reason,
                'deactivated_instances' => $deactivatedInstances,
            ],
        );

        return $license->refresh();
    }
}
