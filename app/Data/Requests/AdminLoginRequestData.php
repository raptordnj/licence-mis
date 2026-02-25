<?php

declare(strict_types=1);

namespace App\Data\Requests;

use Spatie\LaravelData\Data;

class AdminLoginRequestData extends Data
{
    public function __construct(
        public string $email,
        public string $password,
        public ?string $twoFactorCode,
        public ?string $recoveryCode,
    ) {
    }
}
