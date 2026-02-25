<?php

declare(strict_types=1);

namespace App\Data\Domain;

use Spatie\LaravelData\Data;

class AdminLoginInputData extends Data
{
    public function __construct(
        public string $email,
        public string $password,
        public string $ipAddress,
        public ?string $twoFactorCode,
        public ?string $recoveryCode,
    ) {
    }
}
