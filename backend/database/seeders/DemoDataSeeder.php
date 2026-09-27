<?php

namespace Database\Seeders;

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
            ['slug' => 'nby-commerce'],
            ['name' => 'NBY Commerce', 'email' => 'owner@nby.test', 'phone' => '01700000000', 'status' => 'active'],
        );

        $store = Store::updateOrCreate(
            ['slug' => 'nby-flagship-store'],
            [
                'organization_id' => $organization->id,
                'name' => 'NBY Flagship Store',
                'default_currency_id' => $bdt->id,
                'default_timezone' => 'Asia/Dhaka',
                'default_locale' => 'en',
                'status' => 'active',
            ],
        );

        $owner = User::updateOrCreate(
            ['email' => 'admin@nby.test'],
            [
                'name' => 'NBY Super Admin',
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

        Warehouse::updateOrCreate(
            ['store_id' => $store->id, 'code' => 'MAIN-DHK'],
            ['name' => 'Dhaka Main Warehouse', 'type' => 'main', 'status' => 'active'],
        );

        Warehouse::updateOrCreate(
            ['store_id' => $store->id, 'code' => 'BR-CTG'],
            ['name' => 'Chattogram Branch', 'type' => 'branch', 'status' => 'active'],
        );
    }
}
