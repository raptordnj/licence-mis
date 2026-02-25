<?php

declare(strict_types=1);

namespace App\Enums;

enum LicenseType: string
{
    case REGULAR = 'regular';
    case EXTENDED = 'extended';
}
