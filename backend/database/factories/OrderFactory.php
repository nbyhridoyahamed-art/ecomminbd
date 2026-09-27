<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'customer_id' => Customer::factory(),
            'warehouse_id' => Warehouse::factory(),
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'status' => 'pending',
            'payment_method' => 'cod',
            'currency_code' => 'BDT',
            'shipping_recipient_name' => fake()->name(),
            'shipping_phone' => fake()->numerify('01#########'),
            'shipping_address_line' => fake()->address(),
        ];
    }

    public function processing(): static
    {
        return $this->state(['status' => 'processing']);
    }
}
