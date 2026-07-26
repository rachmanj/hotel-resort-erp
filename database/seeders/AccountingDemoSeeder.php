<?php

namespace Database\Seeders;

use App\Models\AccountingPeriod;
use App\Models\Hotel;
use App\Services\Accounting\AccountingPeriodService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AccountingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $periodService = app(AccountingPeriodService::class);

        Hotel::query()->each(function (Hotel $hotel) use ($periodService): void {
            for ($i = 2; $i >= 0; $i--) {
                $start = now()->subMonths($i)->startOfMonth();
                $end = now()->subMonths($i)->endOfMonth();

                $exists = AccountingPeriod::query()
                    ->withoutGlobalScope('hotel')
                    ->where('hotel_id', $hotel->id)
                    ->where('start_date', $start->toDateString())
                    ->exists();

                if ($exists) {
                    continue;
                }

                $periodService->openPeriod(
                    $hotel,
                    $start->format('Y-m'),
                    Carbon::parse($start),
                    Carbon::parse($end),
                );
            }
        });
    }
}
