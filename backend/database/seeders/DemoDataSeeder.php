<?php

namespace Database\Seeders;

use App\Models\BdDistrict;
use App\Models\Currency;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $bdt = Currency::updateOrCreate(
            ['code' => 'BDT'],
            ['symbol' => '৳', 'name' => 'Bangladeshi Taka', 'decimal_places' => 2, 'is_default' => true, 'status' => 'active'],
        );

        $organization = Organization::updateOrCreate(
            ['slug' => 'eleventory'],
            ['name' => 'Eleventory', 'email' => 'owner@eleventory.test', 'phone' => '01700000000', 'status' => 'active'],
        );

        $store = Store::updateOrCreate(
            ['slug' => 'eleventory-flagship-store'],
            [
                'organization_id' => $organization->id,
                'name' => 'Eleventory Flagship Store',
                'default_currency_id' => $bdt->id,
                'default_timezone' => 'Asia/Dhaka',
                'default_locale' => 'en',
                'status' => 'active',
            ],
        );

        $owner = User::updateOrCreate(
            ['email' => 'admin@eleventory.test'],
            [
                'name' => 'Eleventory Super Admin',
                'phone' => '01711111111',
                'password' => Hash::make('password'),
                'current_store_id' => $store->id,
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );

        $owner->syncRoles(['Super Admin']);

        $store->users()->syncWithoutDetaching([
            $owner->id => ['is_owner' => true, 'status' => 'active'],
        ]);

        $dhaka = BdDistrict::where('code', 'DHAKA')->first();
        $chattogram = BdDistrict::where('code', 'CHATTOGRAM')->first();
        $sylhet = BdDistrict::where('code', 'SYLHET')->first();

        Warehouse::updateOrCreate(
            ['store_id' => $store->id, 'code' => 'MAIN-DHK'],
            [
                'name' => 'Dhaka Main Warehouse',
                'type' => 'main',
                'manager_name' => 'Abdullah Al Mamun',
                'phone' => '01711000001',
                'address_line' => 'Plot 14, Tejgaon Industrial Area',
                'bd_division_id' => $dhaka?->bd_division_id,
                'bd_district_id' => $dhaka?->id,
                'status' => 'active',
            ],
        );

        Warehouse::updateOrCreate(
            ['store_id' => $store->id, 'code' => 'BR-CTG'],
            [
                'name' => 'Chattogram Branch',
                'type' => 'branch',
                'manager_name' => 'Nasir Uddin',
                'phone' => '01711000002',
                'address_line' => 'GEC Circle, Chattogram',
                'bd_division_id' => $chattogram?->bd_division_id,
                'bd_district_id' => $chattogram?->id,
                'status' => 'active',
            ],
        );

        Warehouse::updateOrCreate(
            ['store_id' => $store->id, 'code' => 'BR-SYL'],
            [
                'name' => 'Sylhet Branch',
                'type' => 'branch',
                'manager_name' => 'Farhana Yasmin',
                'phone' => '01711000003',
                'address_line' => 'Zindabazar, Sylhet Sadar',
                'bd_division_id' => $sylhet?->bd_division_id,
                'bd_district_id' => $sylhet?->id,
                'status' => 'active',
            ],
        );
    }
}
