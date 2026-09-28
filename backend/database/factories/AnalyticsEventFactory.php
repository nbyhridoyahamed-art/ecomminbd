<?php

namespace Database\Factories;

use App\Models\AnalyticsEvent;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AnalyticsEvent>
 */
class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'session_id' => (string) Str::uuid(),
            'event_type' => 'page_view',
            'entity_type' => null,
            'entity_id' => null,
            'path' => '/',
            'metadata' => null,
        ];
    }

    public function productView(int $productId): static
    {
        return $this->state(fn () => [
            'event_type' => 'product_view',
            'entity_type' => Product::class,
            'entity_id' => $productId,
        ]);
    }

    public function search(string $query, int $resultsCount = 0): static
    {
        return $this->state(fn () => [
            'event_type' => 'search',
            'path' => '/products',
            'metadata' => ['query' => $query, 'results_count' => $resultsCount],
        ]);
    }
}
