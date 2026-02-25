<?php

declare(strict_types=1);

namespace App\Data\Responses;

use Spatie\LaravelData\Data;

class PublicLicenseVerifyResponseData extends Data
{
    public function __construct(
        public string $status,
        public ?string $reason,
        public int $valid_until,
        public int $issued_at,
        public string $instance_id,
        public string $domain,
        public int $product_id,
        public string $token,
    ) {
    }
}
