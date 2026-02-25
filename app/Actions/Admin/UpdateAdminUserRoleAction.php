<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Domain\UpdateAdminUserRoleInputData;
use App\Enums\AuditEventType;
use App\Models\User;
use App\Services\AuditLogService;

readonly class UpdateAdminUserRoleAction
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function execute(User $actor, User $target, UpdateAdminUserRoleInputData $input): User
    {
        $previousRole = $target->role;

        if ($previousRole === $input->role) {
            return $target;
        }

        $target->forceFill([
            'role' => $input->role,
        ])->save();

        $this->auditLogService->log(
            eventType: AuditEventType::ROLE_CHANGED,
            actor: $actor,
            licenseId: null,
            metadata: [
                'target_user_id' => $target->id,
                'target_email' => $target->email,
                'previous_role' => $previousRole->value,
                'new_role' => $target->role->value,
                'change_type' => 'role_update',
            ],
        );

        return $target->refresh();
    }
}
