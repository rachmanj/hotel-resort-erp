<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class PratasabaRoomTypeSeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $this->seedForHotel($hotel);
        });
    }

    private function seedForHotel(Hotel $hotel): void
    {
        $roomTypes = [
            [
                'code' => 'SRJ',
                'name' => 'Seroja',
                'max_occupancy' => 4,
                'base_rate' => 1500000.00,
                'description' => 'Suite',
            ],
            [
                'code' => 'KSL',
                'name' => 'Kasilasa',
                'max_occupancy' => 2,
                'base_rate' => 1200000.00,
                'description' => 'Grand Deluxe',
            ],
            [
                'code' => 'SHK',
                'name' => 'Seheku',
                'max_occupancy' => 2,
                'base_rate' => 850000.00,
                'description' => 'Deluxe — available in seaview and gardenview',
            ],
            [
                'code' => 'JNT',
                'name' => 'Janti',
                'max_occupancy' => 2,
                'base_rate' => 600000.00,
                'description' => 'Standard',
            ],
        ];

        foreach ($roomTypes as $roomType) {
            RoomType::query()->updateOrCreate(
                ['code' => $roomType['code']],
                [
                    'hotel_id' => $hotel->id,
                    'name' => $roomType['name'],
                    'max_occupancy' => $roomType['max_occupancy'],
                    'base_rate' => $roomType['base_rate'],
                    'description' => $roomType['description'],
                    'is_active' => true,
                ],
            );
        }
    }
}
