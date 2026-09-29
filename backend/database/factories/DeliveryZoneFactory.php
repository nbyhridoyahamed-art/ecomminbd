<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryZone>
 */
class DeliveryZoneFactory extends Factory
{
    protected $model = DeliveryZone::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => 'Inside Dhaka',
            'bd_division_id' => null,
            'bd_district_id' => null,
            'status' => 'active',
        ];
    }

    /** A single flat base-rate tier, so a zone works out of the box in tests without spelling out rates every time. */
    public function configure(): static
    {
        return $this->afterCreating(function (DeliveryZone $zone) {
            if ($zone->rates()->count() === 0) {
                $zone->rates()->create(['min_order_subtotal_amount' => 0, 'rate_amount' => 6000, 'currency_code' => 'BDT']);
            }
        });
    }
}
