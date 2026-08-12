<?php

namespace App\Actions\Groups;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\GroupBookingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RemoveReservationFromGroupAction
{
    public function __construct(
        private GroupBookingService $groupBookingService,
    ) {}

    public function __invoke(ReservationGroup $group, Reservation $reservation, ?User $performedBy = null): void
    {
        DB::transaction(function () use ($group, $reservation, $performedBy): void {
            if ($reservation->reservation_group_id !== $group->id) {
                throw new InvalidArgumentException('Reservation does not belong to this group.');
            }

            if ($reservation->status === ReservationStatus::CheckedIn) {
                throw new InvalidArgumentException('Cannot remove a checked-in reservation. Check out first.');
            }

            $reservation->update(['reservation_group_id' => null]);

            $this->groupBookingService->syncGroupDates($group->fresh());
            $this->groupBookingService->refreshGroupStatus($group->fresh());

            if ($performedBy !== null) {
                ActivityLogObserver::logCustom(
                    $group,
                    'reservation_removed',
                    "Reservation {$reservation->reservation_code} removed from group {$group->group_code} by {$performedBy->name}",
                    $performedBy->id,
                );
            }
        });
    }
}
