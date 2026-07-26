<?php

namespace App\Observers;

use App\Models\HousekeepingLog;
use App\Services\HousekeepingService;

class HousekeepingLogObserver
{
    public function __construct(
        private HousekeepingService $housekeepingService,
    ) {}

    public function created(HousekeepingLog $log): void
    {
        $room = $log->room;

        if ($room !== null) {
            $this->housekeepingService->syncRoomStatus($room);
        }
    }
}
