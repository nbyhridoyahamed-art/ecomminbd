<?php

namespace Database\Seeders;

use App\Models\BdDivision;
use App\Models\Courier;
use App\Models\DeliveryZone;
use App\Models\Store;
use Illuminate\Database\Seeder;

/**
 * The courier companies and delivery-zone rate tiers a real Bangladeshi
 * store would actually use — the well-known nationwide courier operators,
 * and flat rate tiers matching what they typically charge (cheaper inside
 * Dhaka, more for other metros, most for the rest of the country).
 */
class DemoDeliverySeeder extends Seeder
{
    public function run(): void
    {
        $store = Store::where('slug', 'eleventory-flagship-store')->firstOrFail();

        collect([
            ['Pathao Courier', 'Delivery Operations', '01755111001'],
            ['Sundarban Courier Service', 'Dispatch Desk', '01755111002'],
            ['RedX', 'Delivery Operations', '01755111003'],
            ['Steadfast Courier', 'Dispatch Desk', '01755111004'],
            ['eCourier', 'Delivery Operations', '01755111005'],
            ['Paperfly', 'Dispatch Desk', '01755111006'],
        ])->each(fn (array $row) => Courier::updateOrCreate(
            ['store_id' => $store->id, 'name' => $row[0]],
            ['contact_name' => $row[1], 'phone' => $row[2], 'status' => 'active'],
        ));

        $divisions = BdDivision::whereIn('code', ['DHK', 'CTG', 'SYL'])->get()->keyBy('code');

        $zones = [
            ['Inside Dhaka', $divisions['DHK']->id ?? null, 6000],
            ['Chattogram Metro', $divisions['CTG']->id ?? null, 8000],
            ['Sylhet Region', $divisions['SYL']->id ?? null, 9000],
            ['Rest of Bangladesh', null, 13000],
        ];

        foreach ($zones as [$name, $divisionId, $rateAmount]) {
            $zone = DeliveryZone::updateOrCreate(
                ['store_id' => $store->id, 'name' => $name],
                ['bd_division_id' => $divisionId, 'status' => 'active'],
            );

            if ($zone->rates()->count() === 0) {
                $zone->rates()->create([
                    'min_order_subtotal_amount' => 0,
                    'rate_amount' => $rateAmount,
                    'currency_code' => 'BDT',
                ]);
            }
        }
    }
}
