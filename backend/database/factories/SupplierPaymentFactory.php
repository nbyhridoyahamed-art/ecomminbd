<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPayment>
 */
class SupplierPaymentFactory extends Factory
{
    protected $model = SupplierPayment::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'supplier_id' => Supplier::factory(),
            'amount_amount' => fake()->numberBetween(10000, 500000),
            'currency_code' => 'BDT',
            'method' => 'bank_transfer',
        ];
    }
}
