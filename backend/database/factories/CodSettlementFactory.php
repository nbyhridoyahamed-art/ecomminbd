<?php

namespace Database\Factories;

use App\Models\CodSettlement;
use App\Models\Courier;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CodSettlement>
 */
class CodSettlementFactory extends Factory
{
    protected $model = CodSettlement::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'courier_id' => Courier::factory(),
            'settlement_number' => 'CODS-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'amount_expected' => 0,
            'amount_received' => 0,
        ];
    }
}
