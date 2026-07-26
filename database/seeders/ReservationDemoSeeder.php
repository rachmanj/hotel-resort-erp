<?php

namespace Database\Seeders;

use App\Enums\RatePlanType;
use App\Enums\RoomStatus;
use App\Models\Floor;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Season;
use Illuminate\Database\Seeder;

class ReservationDemoSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::query()->where('code', 'GNB')->first();

        if ($hotel === null) {
            return;
        }

        $deluxe = RoomType::query()->updateOrCreate(
            ['code' => 'DLX'],
            [
                'hotel_id' => $hotel->id,
                'name' => 'Deluxe Room',
                'max_occupancy' => 2,
                'base_rate' => 850000.00,
                'description' => 'Comfortable deluxe room with garden view',
                'is_active' => true,
            ]
        );

        $suite = RoomType::query()->updateOrCreate(
            ['code' => 'STE'],
            [
                'hotel_id' => $hotel->id,
                'name' => 'Suite',
                'max_occupancy' => 4,
                'base_rate' => 1500000.00,
                'description' => 'Spacious suite with living area',
                'is_active' => true,
            ]
        );

        $floor1 = Floor::query()->updateOrCreate(
            ['hotel_id' => $hotel->id, 'name' => 'Lantai 1'],
            ['level' => 1]
        );

        $floor2 = Floor::query()->updateOrCreate(
            ['hotel_id' => $hotel->id, 'name' => 'Lantai 2'],
            ['level' => 2]
        );

        $deluxeRooms = ['101', '102', '103', '104', '105'];
        foreach ($deluxeRooms as $number) {
            Room::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'number' => $number],
                [
                    'room_type_id' => $deluxe->id,
                    'floor_id' => $floor1->id,
                    'status' => RoomStatus::VacantClean->value,
                ]
            );
        }

        $suiteRooms = ['201', '202', '203', '204', '205'];
        foreach ($suiteRooms as $number) {
            Room::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'number' => $number],
                [
                    'room_type_id' => $suite->id,
                    'floor_id' => $floor2->id,
                    'status' => RoomStatus::VacantClean->value,
                ]
            );
        }

        $season = Season::query()->updateOrCreate(
            ['name' => 'High Season Jul-Agustus'],
            [
                'start_date' => '2026-07-01',
                'end_date' => '2026-08-31',
            ]
        );

        RatePlan::query()->updateOrCreate(
            [
                'hotel_id' => $hotel->id,
                'room_type_id' => $deluxe->id,
                'name' => 'Deluxe Standard Rate',
            ],
            [
                'season_id' => null,
                'rate_type' => RatePlanType::Standard->value,
                'nightly_rate' => 850000.00,
                'is_active' => true,
            ]
        );

        RatePlan::query()->updateOrCreate(
            [
                'hotel_id' => $hotel->id,
                'room_type_id' => $suite->id,
                'name' => 'Suite Standard Rate',
            ],
            [
                'season_id' => null,
                'rate_type' => RatePlanType::Standard->value,
                'nightly_rate' => 1500000.00,
                'is_active' => true,
            ]
        );

        RatePlan::query()->updateOrCreate(
            [
                'hotel_id' => $hotel->id,
                'room_type_id' => $deluxe->id,
                'name' => 'Deluxe High Season',
            ],
            [
                'season_id' => $season->id,
                'rate_type' => RatePlanType::Seasonal->value,
                'nightly_rate' => 950000.00,
                'is_active' => true,
            ]
        );
    }
}
