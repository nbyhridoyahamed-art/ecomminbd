<?php

namespace Database\Factories;

use App\Models\SavedSection;
use App\Models\Store;
use App\Support\HomepageBlockTypes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSection>
 */
class SavedSectionFactory extends Factory
{
    protected $model = SavedSection::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'type' => HomepageBlockTypes::RICH_TEXT,
            'settings' => HomepageBlockTypes::defaultSettings(HomepageBlockTypes::RICH_TEXT),
        ];
    }
}
