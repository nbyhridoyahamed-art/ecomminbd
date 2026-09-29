<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'code' => Str::upper(Str::random(8)),
            'discount_type' => 'percentage',
            'percentage_value' => 10,
            'fixed_discount_amount' => null,
            'currency_code' => 'BDT',
            'minimum_order_amount' => 0,
            'usage_limit' => null,
            'used_count' => 0,
            'per_customer_limit' => null,
            'status' => 'active',
        ];
    }

    public function fixed(int $amountMinor): static
    {
        return $this->state(['discount_type' => 'fixed', 'percentage_value' => null, 'fixed_discount_amount' => $amountMinor]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
