<?php

namespace Database\Factories;

use App\Models\BlogTag;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogTag>
 */
class BlogTagFactory extends Factory
{
    protected $model = BlogTag::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => ucfirst(fake()->word()),
            'slug' => fake()->unique()->slug(1),
        ];
    }
}
