<?php

namespace Database\Seeders;

use App\Enums\DivePackageType;
use App\Models\BoatUnit;
use App\Models\DivePackage;
use App\Models\Hotel;
use Illuminate\Database\Seeder;

class DiveCenterSeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $this->seedForHotel($hotel);
        });
    }

    private function seedForHotel(Hotel $hotel): void
    {
        $packages = [
            [
                'code' => 'DV-PKG-SOLO',
                'name' => 'Dive Package (Solo)',
                'type' => DivePackageType::DivePackage->value,
                'price_per_person' => 2000000,
                'min_pax' => 1,
                'includes' => 'Per day, 3x dives, incl. weight, tank, dive guide, snack, coffee/tea',
            ],
            [
                'code' => 'DV-PKG-GRP',
                'name' => 'Dive Package (Group)',
                'type' => DivePackageType::DivePackage->value,
                'price_per_person' => 1500000,
                'min_pax' => 2,
                'includes' => null,
            ],
            [
                'code' => 'DV-NIGHT',
                'name' => 'Night Dive',
                'type' => DivePackageType::NightDive->value,
                'price_per_person' => 600000,
                'min_pax' => 1,
                'includes' => null,
            ],
            [
                'code' => 'DV-DSD-GUEST',
                'name' => 'Discover Scuba Diving (Guest Stay)',
                'type' => DivePackageType::DiscoveryScuba->value,
                'price_per_person' => 800000,
                'min_pax' => 1,
                'includes' => null,
            ],
            [
                'code' => 'DV-DSD-VISITOR',
                'name' => 'Discover Scuba Diving (Visitor)',
                'type' => DivePackageType::DiscoveryScuba->value,
                'price_per_person' => 1000000,
                'min_pax' => 1,
                'includes' => null,
            ],
        ];

        foreach ($packages as $package) {
            DivePackage::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'code' => $package['code']],
                [
                    'name' => $package['name'],
                    'type' => $package['type'],
                    'price_per_person' => $package['price_per_person'],
                    'min_pax' => $package['min_pax'],
                    'includes' => $package['includes'],
                    'is_active' => true,
                ],
            );
        }

        $boats = [
            [
                'code' => 'SB-40',
                'name' => 'Small Boat (40 PK)',
                'capacity' => 3,
                'engine_hp' => 40,
                'is_own' => true,
            ],
            [
                'code' => 'MB-200',
                'name' => 'Medium Boat (200 PK)',
                'capacity' => 12,
                'engine_hp' => 200,
                'is_own' => true,
            ],
        ];

        foreach ($boats as $boat) {
            BoatUnit::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'code' => $boat['code']],
                [
                    'name' => $boat['name'],
                    'capacity' => $boat['capacity'],
                    'engine_hp' => $boat['engine_hp'],
                    'is_own' => $boat['is_own'],
                    'is_active' => true,
                ],
            );
        }
    }
}
