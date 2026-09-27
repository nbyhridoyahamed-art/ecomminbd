<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->word().' Warehouse',
            'code' => strtoupper(fake()->unique()->bothify('WH-###')),
            'type' => 'main',
            'status' => 'active',
        ];
    }
}
