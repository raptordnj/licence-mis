<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LicenseCheckResult;
use App\Models\LicenseCheck;
use App\Models\LicenseInstance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LicenseCheck>
 */
class LicenseCheckFactory extends Factory
{
    protected $model = LicenseCheck::class;

    public function definition(): array
    {
        return [
            'license_instance_id' => LicenseInstance::factory(),
            'checked_at' => now(),
            'result' => LicenseCheckResult::VALID,
            'reason' => null,
            'request_payload' => [],
            'response_payload' => [],
        ];
    }
}
