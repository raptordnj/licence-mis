<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\UpdateRelease;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UpdateRelease>
 */
class UpdateReleaseFactory extends Factory
{
    protected $model = UpdateRelease::class;

    public function definition(): array
    {
        $major = fake()->numberBetween(1, 6);
        $minor = fake()->numberBetween(0, 9);
        $patch = fake()->numberBetween(0, 30);

        return [
            'product_id' => Product::factory(),
            'channel' => 'stable',
            'version' => "{$major}.{$minor}.{$patch}",
            'min_version' => null,
            'max_version' => null,
            'release_notes' => fake()->sentence(),
            'package_path' => 'updates/releases/'.fake()->uuid().'.zip',
            'checksum' => hash('sha256', fake()->uuid()),
            'size_bytes' => fake()->numberBetween(10_000, 5_000_000),
            'is_published' => false,
            'published_at' => null,
            'created_by' => User::factory(),
            'metadata' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function global(): static
    {
        return $this->state(fn (): array => [
            'product_id' => null,
        ]);
    }
}
