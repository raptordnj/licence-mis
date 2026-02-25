<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class EnvatoUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Envato verification service is unavailable.')
    {
        parent::__construct($message);
    }
}
