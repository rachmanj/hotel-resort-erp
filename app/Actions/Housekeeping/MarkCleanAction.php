<?php

namespace App\Actions\Housekeeping;

use App\Enums\HousekeepingAssignmentStatus;
use App\Enums\HousekeepingStatus;
use App\Models\Room;
use App\Models\User;
use App\Services\HousekeepingService;
use InvalidArgumentException;

class MarkCleanAction
{
    public function __construct(
        private HousekeepingService $housekeepingService,
    ) {}

    public function __invoke(Room $room, User $performedBy, string $via = 'web'): void
    {
        $currentStatus = $this->housekeepingService->resolveHousekeepingStatus($room);

        if ($currentStatus !== HousekeepingStatus::Cleaning) {
            throw new InvalidArgumentException('Room must be in cleaning status before marking clean.');
        }

        $this->housekeepingService->logStatusChange(
            $room,
            HousekeepingStatus::Clean->value,
            $performedBy,
            $via,
        );

        $this->housekeepingService->updateAssignmentStatus($room, HousekeepingAssignmentStatus::Done);
    }
}
