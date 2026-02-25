<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'envato_item_id' => $this->faker->unique()->numberBetween(1000, 900000),
            'name' => $this->faker->sentence(3),
            'activation_limit' => $this->faker->numberBetween(1, 3),
            'status' => ProductStatus::ACTIVE,
            'strict_domain_binding' => true,
        ];
    }
}
