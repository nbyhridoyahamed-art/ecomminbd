<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OrderReturn>
 */
class OrderReturnFactory extends Factory
{
    protected $model = OrderReturn::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'order_id' => Order::factory(),
            'return_number' => 'RET-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'status' => 'requested',
        ];
    }
}
