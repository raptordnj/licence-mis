<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class LicenseRevokedException extends RuntimeException
{
    public function __construct(string $message = 'License has been revoked.')
    {
        parent::__construct($message);
    }
}
