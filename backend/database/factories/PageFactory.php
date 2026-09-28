<?php

namespace Database\Factories;

use App\Models\Page;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'title' => ucfirst(fake()->words(3, true)),
            'slug' => fake()->unique()->slug(),
            'content' => fake()->paragraphs(3, true),
            'status' => 'draft',
        ];
    }
}
