<?php

declare(strict_types=1);

namespace App\Enums;

enum EnvatoItemStatus: string
{
    case ACTIVE = 'active';
    case DISABLED = 'disabled';
}
