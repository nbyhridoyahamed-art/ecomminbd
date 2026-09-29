<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'order_id' => Order::factory(),
            'amount_amount' => fake()->numberBetween(10000, 500000),
            'currency_code' => 'BDT',
            'method' => 'bkash',
        ];
    }
}
