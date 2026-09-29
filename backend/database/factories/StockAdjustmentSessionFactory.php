<?php

namespace Database\Factories;

use App\Models\StockAdjustmentSession;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StockAdjustmentSession>
 */
class StockAdjustmentSessionFactory extends Factory
{
    protected $model = StockAdjustmentSession::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'warehouse_id' => Warehouse::factory(),
            'reference' => 'ADJ-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
        ];
    }
}
