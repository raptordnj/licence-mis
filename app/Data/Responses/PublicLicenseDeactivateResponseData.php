<?php

declare(strict_types=1);

namespace App\Data\Responses;

use Spatie\LaravelData\Data;

class PublicLicenseDeactivateResponseData extends Data
{
    public function __construct(
        public bool $success,
        public ?string $reason,
        public int $issued_at,
        public int $valid_until,
        public string $instance_id,
        public int $product_id,
        public string $token,
    ) {
    }
}
