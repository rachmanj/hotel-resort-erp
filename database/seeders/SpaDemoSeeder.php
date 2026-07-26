<?php

namespace Database\Seeders;

use App\Models\Hotel;
use App\Models\SpaTherapist;
use App\Models\SpaTherapistSchedule;
use App\Models\SpaTreatment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SpaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $hotel = Hotel::query()->where('code', 'GNB')->first();

        if ($hotel === null) {
            return;
        }

        $spaUser = User::query()->updateOrCreate(
            ['email' => 'spa@hotel.test'],
            [
                'name' => 'Dewi Spa',
                'password' => Hash::make('password'),
                'hotel_id' => $hotel->id,
            ],
        );
        $spaUser->syncRoles(['spa']);
        $hotel->users()->syncWithoutDetaching([$spaUser->id]);

        $treatments = [
            ['name' => 'Traditional Balinese Massage', 'duration_minutes' => 60, 'price' => 350000, 'description' => 'Full body massage with aromatic oils'],
            ['name' => 'Hot Stone Therapy', 'duration_minutes' => 90, 'price' => 500000, 'description' => 'Volcanic stones and deep tissue work'],
            ['name' => 'Facial Rejuvenation', 'duration_minutes' => 45, 'price' => 275000, 'description' => 'Cleansing and hydrating facial treatment'],
            ['name' => 'Couples Spa Package', 'duration_minutes' => 120, 'price' => 950000, 'description' => 'Side-by-side massage for two guests'],
        ];

        foreach ($treatments as $treatment) {
            SpaTreatment::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'name' => $treatment['name']],
                $treatment + ['hotel_id' => $hotel->id],
            );
        }

        $therapists = [
            ['name' => 'Putu Ayu', 'phone' => '081234567801', 'user_id' => $spaUser->id],
            ['name' => 'Made Sari', 'phone' => '081234567802', 'user_id' => null],
            ['name' => 'Ketut Wulan', 'phone' => '081234567803', 'user_id' => null],
        ];

        foreach ($therapists as $therapistData) {
            $therapist = SpaTherapist::query()->updateOrCreate(
                ['hotel_id' => $hotel->id, 'name' => $therapistData['name']],
                $therapistData + ['hotel_id' => $hotel->id],
            );

            for ($day = 0; $day < 7; $day++) {
                $workDate = now()->addDays($day)->toDateString();
                SpaTherapistSchedule::query()->updateOrCreate(
                    [
                        'spa_therapist_id' => $therapist->id,
                        'work_date' => $workDate,
                    ],
                    [
                        'start_time' => '09:00:00',
                        'end_time' => '17:00:00',
                    ],
                );
            }
        }
    }
}
