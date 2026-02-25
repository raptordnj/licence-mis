<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class PurchaseInvalidException extends RuntimeException
{
    public function __construct(string $message = 'Purchase code is invalid.')
    {
        parent::__construct($message);
    }
}
