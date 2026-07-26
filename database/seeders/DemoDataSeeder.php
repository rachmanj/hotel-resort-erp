<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            HotelCurrencySeeder::class,
            ChartOfAccountsSeeder::class,
            AccountingDemoSeeder::class,
            AccountingExtensionSeeder::class,
            BillingDemoSeeder::class,
            ReservationDemoSeeder::class,
            HousekeepingDemoSeeder::class,
            MenuCategorySeeder::class,
            RestaurantTableSeeder::class,
            InventoryDemoSeeder::class,
            SpaDemoSeeder::class,
        ]);
    }
}
