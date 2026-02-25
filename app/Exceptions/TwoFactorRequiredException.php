<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class TwoFactorRequiredException extends RuntimeException
{
    public function __construct(string $message = 'Two-factor code or recovery code is required.')
    {
        parent::__construct($message);
    }
}
