<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SeoMetadata;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoMetadata>
 */
class SeoMetadataFactory extends Factory
{
    protected $model = SeoMetadata::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'entity_type' => Product::class,
            'entity_id' => Product::factory(),
            'title' => ucfirst(fake()->words(4, true)),
            'description' => fake()->sentence(15),
            'focus_keyword' => fake()->word(),
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'twitter_title' => null,
            'twitter_description' => null,
            'twitter_image' => null,
            'canonical_url' => null,
            'robots' => null,
            'schema_json' => null,
        ];
    }
}
