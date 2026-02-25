<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class DomainMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $expectedDomain,
        public readonly string $providedDomain,
    ) {
        parent::__construct('License is bound to a different domain.');
    }
}
