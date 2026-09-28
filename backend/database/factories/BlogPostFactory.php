<?php

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'title' => ucfirst(fake()->sentence(4)),
            'slug' => fake()->unique()->slug(),
            'excerpt' => fake()->paragraph(),
            'featured_image_url' => null,
            'published_at' => now(),
            'is_active' => true,
        ];
    }
}
