<?php

declare(strict_types=1);

namespace App\Data\Domain;

use Spatie\LaravelData\Data;

class UpdateSensitiveSettingsInputData extends Data
{
    public function __construct(
        public ?string $envatoApiToken,
        public ?string $licenseHmacKey,
        public ?bool $envatoMockMode,
    ) {
    }
}
