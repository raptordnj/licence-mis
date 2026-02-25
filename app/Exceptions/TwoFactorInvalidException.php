<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class TwoFactorInvalidException extends RuntimeException
{
    public function __construct(string $message = 'Provided two-factor verification code is invalid.')
    {
        parent::__construct($message);
    }
}
