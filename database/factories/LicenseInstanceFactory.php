<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LicenseInstanceStatus;
use App\Models\License;
use App\Models\LicenseInstance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LicenseInstance>
 */
class LicenseInstanceFactory extends Factory
{
    protected $model = LicenseInstance::class;

    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'instance_id' => (string) Str::uuid(),
            'domain' => $this->faker->domainName(),
            'app_url' => $this->faker->url(),
            'ip' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'last_seen_at' => now(),
            'activated_at' => now(),
            'deactivated_at' => null,
            'status' => LicenseInstanceStatus::ACTIVE,
        ];
    }
}
