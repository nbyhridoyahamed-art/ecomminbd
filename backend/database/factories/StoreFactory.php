<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->company().' Store',
            'slug' => fake()->unique()->slug(),
            'default_timezone' => 'Asia/Dhaka',
            'default_locale' => 'en',
            'status' => 'active',
        ];
    }
}
