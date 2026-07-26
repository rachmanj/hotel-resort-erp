<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('hotel:check-no-shows')->hourly();
Schedule::command('housekeeping:generate-assignments')->dailyAt('06:00');
Schedule::command('inventory:check-low-stock')->hourly();
