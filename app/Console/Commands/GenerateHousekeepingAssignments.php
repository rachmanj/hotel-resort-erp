<?php

namespace App\Console\Commands;

use App\Services\HousekeepingService;
use Illuminate\Console\Command;

class GenerateHousekeepingAssignments extends Command
{
    protected $signature = 'housekeeping:generate-assignments {--hotel= : Hotel ID to generate for}';

    protected $description = 'Generate daily housekeeping assignments for checkout and stay-over rooms';

    public function handle(HousekeepingService $housekeepingService): int
    {
        $hotelId = $this->option('hotel') !== null ? (int) $this->option('hotel') : null;

        $assignments = $housekeepingService->generateDailyAssignments($hotelId);

        $this->info("Generated {$assignments->count()} housekeeping assignment(s).");

        return self::SUCCESS;
    }
}
