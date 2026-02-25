<?php

declare(strict_types=1);

namespace App\Enums;

enum RoleName: string
{
    case SUPER_ADMIN = 'super-admin';
    case ADMIN = 'admin';
    case SUPPORT = 'support';
}
