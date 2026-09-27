<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 20);

        return [
            'store_id' => Store::factory(),
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'type' => 'adjustment_increase',
            'quantity' => $quantity,
            'quantity_before' => 0,
            'quantity_after' => $quantity,
            'reason' => fake()->sentence(3),
        ];
    }
}
