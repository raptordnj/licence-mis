<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\AuditEventType;
use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\User;
use App\Services\AuditLogService;

readonly class RevokeLicenseAction
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function execute(License $license, User $actor, ?string $reason = null): License
    {
        $license->forceFill([
            'status' => LicenseStatus::REVOKED,
        ])->save();

        $this->auditLogService->log(
            eventType: AuditEventType::LICENSE_REVOKED,
            actor: $actor,
            licenseId: $license->id,
            metadata: [
                'reason' => $reason,
            ],
        );

        return $license->refresh();
    }
}
