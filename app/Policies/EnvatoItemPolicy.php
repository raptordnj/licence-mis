<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\EnvatoItem;
use App\Models\User;

class EnvatoItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->role === RoleName::SUPPORT;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, EnvatoItem $envatoItem): bool
    {
        return $user->isAdmin();
    }
}
