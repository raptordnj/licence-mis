<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LicenseStatus;
use App\Enums\Marketplace;
use App\Models\License;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<License>
 */
class LicenseFactory extends Factory
{
    protected $model = License::class;

    public function definition(): array
    {
        return [
            'product_id' => null,
            'purchase_code' => $this->faker->unique()->uuid(),
            'buyer' => $this->faker->userName(),
            'marketplace' => Marketplace::ENVATO,
            'envato_item_id' => $this->faker->numberBetween(1000, 9999),
            'status' => LicenseStatus::ACTIVE,
            'notes' => null,
            'bound_domain' => $this->faker->domainName(),
            'supported_until' => now()->addYear(),
            'verified_at' => now(),
            'metadata' => [
                'item_name' => $this->faker->word(),
                'buyer' => $this->faker->userName(),
                'buyer_username' => $this->faker->userName(),
                'license_type' => $this->faker->randomElement(['regular', 'extended']),
                'version' => '4.7.11',
            ],
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'product_id' => $product->id,
            'envato_item_id' => $product->envato_item_id,
        ]);
    }
}
