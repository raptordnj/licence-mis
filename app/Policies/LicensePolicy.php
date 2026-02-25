<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\License;
use App\Models\User;

class LicensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->role === RoleName::SUPPORT;
    }

    public function revoke(User $user, License $license): bool
    {
        return $user->isAdmin();
    }

    public function resetDomain(User $user, License $license): bool
    {
        return $user->isAdmin();
    }
}
