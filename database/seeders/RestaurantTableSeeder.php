<?php

namespace Database\Seeders;

use App\Enums\RestaurantTableArea;
use App\Enums\RestaurantTableStatus;
use App\Models\Hotel;
use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;

class RestaurantTableSeeder extends Seeder
{
    public function run(): void
    {
        $hotels = Hotel::query()->get();

        foreach ($hotels as $hotel) {
            $tables = [
                ['name' => 'T-01', 'area' => RestaurantTableArea::Indoor, 'capacity' => 2],
                ['name' => 'T-02', 'area' => RestaurantTableArea::Indoor, 'capacity' => 4],
                ['name' => 'T-03', 'area' => RestaurantTableArea::Indoor, 'capacity' => 4],
                ['name' => 'T-04', 'area' => RestaurantTableArea::Terrace, 'capacity' => 6],
                ['name' => 'T-05', 'area' => RestaurantTableArea::Terrace, 'capacity' => 4],
                ['name' => 'P-01', 'area' => RestaurantTableArea::Poolside, 'capacity' => 2],
                ['name' => 'P-02', 'area' => RestaurantTableArea::Poolside, 'capacity' => 4],
            ];

            foreach ($tables as $table) {
                RestaurantTable::query()->firstOrCreate(
                    ['hotel_id' => $hotel->id, 'name' => $table['name']],
                    [
                        'area' => $table['area']->value,
                        'capacity' => $table['capacity'],
                        'status' => RestaurantTableStatus::Available->value,
                    ],
                );
            }
        }
    }
}
