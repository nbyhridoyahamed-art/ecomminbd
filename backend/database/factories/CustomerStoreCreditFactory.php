<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerStoreCredit;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerStoreCredit>
 */
class CustomerStoreCreditFactory extends Factory
{
    protected $model = CustomerStoreCredit::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'customer_id' => Customer::factory(),
            'amount' => fake()->numberBetween(100, 10000),
            'note' => fake()->sentence(4),
        ];
    }
}
