<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SeoTemplate;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoTemplate>
 */
class SeoTemplateFactory extends Factory
{
    protected $model = SeoTemplate::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'entity_type' => Product::class,
            'title_template' => '{{title}} | {{store_name}}',
            'description_template' => '{{excerpt}}',
        ];
    }
}
