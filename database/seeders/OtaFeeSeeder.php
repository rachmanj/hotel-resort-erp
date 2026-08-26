<?php

namespace Database\Seeders;

use App\Enums\OtaFeeType;
use App\Models\Hotel;
use App\Models\OtaFee;
use Illuminate\Database\Seeder;

class OtaFeeSeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $this->seedForHotel($hotel);
        });
    }

    private function seedForHotel(Hotel $hotel): void
    {
        $fees = [
            [
                'code' => 'traveloka',
                'name' => 'Traveloka',
                'fee_type' => OtaFeeType::Percent->value,
                'base_fee_pct' => 19,
                'variable_fee_pct' => 0,
                'flat_fee_per_room_night' => null,
            ],
            [
                'code' => 'tiket',
                'name' => 'Tiket.com',
                'fee_type' => OtaFeeType::Percent->value,
                'base_fee_pct' => 17,
                'variable_fee_pct' => 10,
                'flat_fee_per_room_night' => null,
            ],
            [
                'code' => 'marketing_non_agent',
                'name' => 'Marketing Non-Agent',
                'fee_type' => OtaFeeType::Flat->value,
                'base_fee_pct' => null,
                'variable_fee_pct' => null,
                'flat_fee_per_room_night' => 100000,
            ],
        ];

        foreach ($fees as $fee) {
            OtaFee::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'code' => $fee['code']],
                [
                    'name' => $fee['name'],
                    'fee_type' => $fee['fee_type'],
                    'base_fee_pct' => $fee['base_fee_pct'],
                    'variable_fee_pct' => $fee['variable_fee_pct'],
                    'flat_fee_per_room_night' => $fee['flat_fee_per_room_night'],
                    'is_active' => true,
                ],
            );
        }
    }
}
