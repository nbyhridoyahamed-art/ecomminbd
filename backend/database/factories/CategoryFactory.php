<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => ucfirst(fake()->words(2, true)),
            'slug' => fake()->unique()->slug(),
            'status' => 'active',
        ];
    }
}
