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
            'body' => fake()->paragraphs(4, true),
            'featured_image_url' => null,
            'status' => 'published',
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft', 'published_at' => null]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published', 'published_at' => now()]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['status' => 'published', 'published_at' => now()->addWeek()]);
    }
}
