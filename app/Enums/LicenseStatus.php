<?php

declare(strict_types=1);

namespace App\Enums;

enum LicenseStatus: string
{
    case ACTIVE = 'active';
    case VALID = 'valid';
    case INVALID = 'invalid';
    case REVOKED = 'revoked';
    case REFUNDED = 'refunded';
    case CHARGEBACK = 'chargeback';
    case EXPIRED = 'expired';
}
