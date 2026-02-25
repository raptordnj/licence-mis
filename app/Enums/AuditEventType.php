<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditEventType: string
{
    case LICENSE_REVOKED = 'license_revoked';
    case DOMAIN_RESET = 'domain_reset';
    case TOKEN_CHANGED = 'token_changed';
    case ROLE_CHANGED = 'role_changed';
}
