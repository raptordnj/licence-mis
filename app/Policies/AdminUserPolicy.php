<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class AdminUserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function updateRole(User $user, User $target): bool
    {
        return $user->isSuperAdmin();
    }
}
