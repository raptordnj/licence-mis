<?php

declare(strict_types=1);

namespace App\Data\Requests;

use Spatie\LaravelData\Data;

class UpdateAdminSettingsRequestData extends Data
{
    public function __construct(
        public ?string $envatoApiToken,
        public ?string $licenseHmacKey,
        public ?bool $envatoMockMode,
    ) {
    }
}
