<?php

namespace Database\Factories;

use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StockTransfer>
 */
class StockTransferFactory extends Factory
{
    protected $model = StockTransfer::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'transfer_number' => 'TRF-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'status' => 'pending',
        ];
    }

    public function inTransit(): static
    {
        return $this->state(['status' => 'in_transit']);
    }

    public function received(): static
    {
        return $this->state(['status' => 'received']);
    }
}
