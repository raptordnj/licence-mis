<?php

declare(strict_types=1);

namespace App\Data\Domain;

use Spatie\LaravelData\Data;

class EnvatoVerificationData extends Data
{
    public function __construct(
        public bool $valid,
        public ?int $itemId,
        public ?string $supportedUntil,
        public ?string $itemName,
    ) {
    }
}
