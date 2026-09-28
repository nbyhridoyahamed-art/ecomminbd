<?php

namespace Database\Factories;

use App\Models\HomepageBlock;
use App\Models\Store;
use App\Support\HomepageBlockTypes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HomepageBlock>
 */
class HomepageBlockFactory extends Factory
{
    protected $model = HomepageBlock::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'type' => HomepageBlockTypes::RICH_TEXT,
            'settings' => HomepageBlockTypes::defaultSettings(HomepageBlockTypes::RICH_TEXT),
            'sort_order' => 0,
            'is_active' => false,
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'settings' => HomepageBlockTypes::defaultSettings($type),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['is_active' => true]);
    }
}
