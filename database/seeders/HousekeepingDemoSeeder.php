<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HousekeepingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::query()->where('code', 'GNB')->first();

        if ($hotel === null) {
            return;
        }

        $rina = User::query()->updateOrCreate(
            ['email' => 'housekeeping@hotel.test'],
            [
                'name' => 'Rina Housekeeping',
                'password' => Hash::make('password'),
                'hotel_id' => $hotel->id,
            ],
        );
        $rina->syncRoles(['housekeeping']);
        $hotel->users()->syncWithoutDetaching([$rina->id]);

        $budi = User::query()->updateOrCreate(
            ['email' => 'budi.hk@hotel.test'],
            [
                'name' => 'Budi Housekeeping',
                'password' => Hash::make('password'),
                'hotel_id' => $hotel->id,
            ],
        );
        $budi->syncRoles(['housekeeping']);
        $hotel->users()->syncWithoutDetaching([$budi->id]);
    }
}
