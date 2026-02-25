<?php

declare(strict_types=1);

namespace App\Data\Responses;

use Spatie\LaravelData\Data;

class AdminLoginResponseData extends Data
{
    /**
     * @param  array{id: int, name: string, email: string, role: string}  $admin
     */
    public function __construct(
        public string $token,
        public string $tokenType,
        public array $admin,
        public bool $twoFactorEnabled,
    ) {
    }
}
