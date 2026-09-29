<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            BdLocationSeeder::class,
            DemoDataSeeder::class,
            DemoCatalogSeeder::class,
            DemoPurchasingSeeder::class,
            DemoCustomerSeeder::class,
            DemoDeliverySeeder::class,
            DemoContentSeeder::class,
            DemoOrderSeeder::class,
            HomepageBlockSeeder::class,
        ]);
    }
}
