<?php

declare(strict_types=1);

namespace App\Enums;

enum LicenseCheckResult: string
{
    case VALID = 'valid';
    case INVALID = 'invalid';
}
