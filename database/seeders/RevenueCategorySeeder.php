<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\RevenueCategory;
use Illuminate\Database\Seeder;

class RevenueCategorySeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $this->seedForHotel($hotel);
        });
    }

    private function seedForHotel(Hotel $hotel): void
    {
        $categories = [
            ['code' => 'room_seroja', 'name' => 'Seroja (Suite)', 'coa_account_code' => '4-1300', 'sort_order' => 1],
            ['code' => 'room_kasilasa', 'name' => 'Kasilasa (Grand Deluxe)', 'coa_account_code' => '4-1400', 'sort_order' => 2],
            ['code' => 'room_seheku', 'name' => 'Seheku (Deluxe)', 'coa_account_code' => '4-1200', 'sort_order' => 3],
            ['code' => 'room_janti', 'name' => 'Janti (Standard)', 'coa_account_code' => '4-1100', 'sort_order' => 4],
            ['code' => 'dive_center', 'name' => 'Pratasaba Dive Center', 'coa_account_code' => '4-4300', 'sort_order' => 5],
            ['code' => 'boat', 'name' => 'Boat / Rental Speedboat', 'coa_account_code' => '4-4310', 'sort_order' => 6],
            ['code' => 'laundry', 'name' => 'Laundry', 'coa_account_code' => '4-1600', 'sort_order' => 7],
            ['code' => 'transport_car', 'name' => 'Car Maratua / Shuttle Berau', 'coa_account_code' => '4-4700', 'sort_order' => 8],
            ['code' => 'transport_motor', 'name' => 'Motor / Sepeda', 'coa_account_code' => '4-4700', 'sort_order' => 9],
            ['code' => 'meeting', 'name' => 'Meeting Packages', 'coa_account_code' => '4-4800', 'sort_order' => 10],
            ['code' => 'resto', 'name' => 'Saba Resto', 'coa_account_code' => '4-2100', 'sort_order' => 11],
            ['code' => 'coffee', 'name' => 'Prata Coffee', 'coa_account_code' => '4-2500', 'sort_order' => 12],
            ['code' => 'merchandise', 'name' => 'Merchandise', 'coa_account_code' => '4-4400', 'sort_order' => 13],
            ['code' => 'showcase', 'name' => 'Showcase', 'coa_account_code' => '4-4500', 'sort_order' => 14],
            ['code' => 'tiket_pantai', 'name' => 'Tiket Pantai', 'coa_account_code' => '4-4600', 'sort_order' => 15],
            ['code' => 'lain_lain', 'name' => 'Lain-lain', 'coa_account_code' => '4-9000', 'sort_order' => 16],
        ];

        foreach ($categories as $category) {
            RevenueCategory::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'code' => $category['code']],
                [
                    'name' => $category['name'],
                    'coa_account_code' => $category['coa_account_code'],
                    'sort_order' => $category['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}
