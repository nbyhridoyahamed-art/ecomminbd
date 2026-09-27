<?php

namespace Database\Factories;

use App\Models\Courier;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'order_id' => Order::factory(),
            'courier_id' => Courier::factory(),
            'tracking_number' => 'TRK-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'status' => 'pending_pickup',
            'delivery_charge_amount' => 0,
        ];
    }
}
