<?php

declare(strict_types=1);

namespace App\Data\Domain;

use App\Enums\RoleName;
use Spatie\LaravelData\Data;

class CreateAdminUserInputData extends Data
{
    public function __construct(
        public string $name,
        public string $email,
        public RoleName $role,
        public ?string $password,
    ) {
    }
}
