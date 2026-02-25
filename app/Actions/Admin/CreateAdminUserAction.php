<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Data\Domain\CreateAdminUserInputData;
use App\Enums\AuditEventType;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Str;

readonly class CreateAdminUserAction
{
    public function __construct(private AuditLogService $auditLogService)
    {
    }

    public function execute(User $actor, CreateAdminUserInputData $input): User
    {
        $generatedPassword = Str::random(32);

        $created = User::query()->create([
            'name' => $input->name,
            'email' => $input->email,
            'password' => $input->password !== null && $input->password !== ''
                ? $input->password
                : $generatedPassword,
            'role' => $input->role,
            'email_verified_at' => now(),
        ]);

        $this->auditLogService->log(
            eventType: AuditEventType::ROLE_CHANGED,
            actor: $actor,
            licenseId: null,
            metadata: [
                'target_user_id' => $created->id,
                'target_email' => $created->email,
                'previous_role' => null,
                'new_role' => $created->role->value,
                'change_type' => 'create_user',
            ],
        );

        return $created;
    }
}
