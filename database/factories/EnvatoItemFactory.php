<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EnvatoItemStatus;
use App\Enums\Marketplace;
use App\Models\EnvatoItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnvatoItem>
 */
class EnvatoItemFactory extends Factory
{
    protected $model = EnvatoItem::class;

    public function definition(): array
    {
        return [
            'marketplace' => Marketplace::ENVATO,
            'envato_item_id' => $this->faker->unique()->numberBetween(1000, 900000),
            'name' => $this->faker->sentence(3),
            'status' => EnvatoItemStatus::ACTIVE,
        ];
    }
}
