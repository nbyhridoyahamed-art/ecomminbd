<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Store;
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
            'store_id' => Store::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'slug' => fake()->unique()->slug(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-#####')),
            'type' => 'simple',
            'currency_code' => 'BDT',
            'price_amount' => fake()->numberBetween(50000, 500000),
            'status' => 'active',
        ];
    }
}
