<?php

namespace Database\Factories;

use App\Models\Courier;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Courier>
 */
class CourierFactory extends Factory
{
    protected $model = Courier::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => fake()->company().' Courier',
            'contact_name' => fake()->name(),
            'phone' => fake()->numerify('01#########'),
            'status' => 'active',
        ];
    }
}
