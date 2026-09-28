<?php

namespace Database\Factories;

use App\Models\BlogPost;
use App\Models\BlogPostVersion;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogPostVersion>
 */
class BlogPostVersionFactory extends Factory
{
    protected $model = BlogPostVersion::class;

    public function definition(): array
    {
        return [
            'blog_post_id' => BlogPost::factory(),
            'store_id' => Store::factory(),
            'snapshot' => [
                'title' => ucfirst(fake()->sentence(4)),
                'slug' => fake()->unique()->slug(),
                'excerpt' => fake()->paragraph(),
                'body' => fake()->paragraphs(2, true),
                'featured_image_url' => null,
                'meta_title' => null,
                'meta_description' => null,
                'status' => 'draft',
            ],
        ];
    }
}
