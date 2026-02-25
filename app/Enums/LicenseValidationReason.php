<?php

declare(strict_types=1);

namespace App\Enums;

enum LicenseValidationReason: string
{
    case REVOKED = 'revoked';
    case REFUND = 'refund';
    case LIMIT_REACHED = 'limit_reached';
    case DOMAIN_MISMATCH = 'domain_mismatch';
    case NOT_FOUND = 'not_found';
    case BAD_REQUEST = 'bad_request';
    case NONE = 'none';
    case DEACTIVATED = 'deactivated';
}
