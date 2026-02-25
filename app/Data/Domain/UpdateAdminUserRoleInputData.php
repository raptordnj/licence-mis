<?php

declare(strict_types=1);

namespace App\Data\Domain;

use App\Enums\RoleName;
use Spatie\LaravelData\Data;

class UpdateAdminUserRoleInputData extends Data
{
    public function __construct(public RoleName $role)
    {
    }
}
