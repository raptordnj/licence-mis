<?php

declare(strict_types=1);

namespace App\ValueObjects;

readonly class PurchaseCode
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
