<?php

namespace App\Actions\Groups;

use App\Actions\Reservations\CreateReservationAction;
use App\Enums\GroupStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\GroupBookingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AddReservationToGroupAction
{
    public function __construct(
        private CreateReservationAction $createReservation,
        private GroupBookingService $groupBookingService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $reservationData
     */
    public function __invoke(
        ReservationGroup $group,
        ?Reservation $existingReservation = null,
        ?array $reservationData = null,
        ?User $performedBy = null,
    ): Reservation {
        return DB::transaction(function () use ($group, $existingReservation, $reservationData, $performedBy): Reservation {
            if ($group->status === GroupStatus::Cancelled) {
                throw new InvalidArgumentException('Cannot add reservations to a cancelled group.');
            }

            if ($existingReservation !== null) {
                if ($existingReservation->reservation_group_id !== null) {
                    throw new InvalidArgumentException('Reservation is already linked to a group.');
                }

                if ($existingReservation->status === ReservationStatus::CheckedIn) {
                    throw new InvalidArgumentException('Cannot link a checked-in reservation to a group.');
                }

                $existingReservation->update(['reservation_group_id' => $group->id]);
                $reservation = $existingReservation->fresh(['guest', 'reservationRooms.room']);
            } else {
                if ($reservationData === null) {
                    throw new InvalidArgumentException('Reservation data is required when not linking an existing reservation.');
                }

                $reservationData['hotel_id'] = $group->hotel_id;
                $reservationData['reservation_group_id'] = $group->id;
                $reservationData['created_by'] = $performedBy?->id ?? $reservationData['created_by'] ?? null;

                if (! isset($reservationData['guest_id']) && $group->pic_guest_id !== null) {
                    $reservationData['guest_id'] = $group->pic_guest_id;
                }

                $reservation = ($this->createReservation)($reservationData, $performedBy);
            }

            $this->groupBookingService->syncGroupDates($group->fresh());
            $this->groupBookingService->refreshGroupStatus($group->fresh());

            if ($group->status === GroupStatus::Draft) {
                $group->update(['status' => GroupStatus::Confirmed->value]);
            }

            if ($performedBy !== null) {
                ActivityLogObserver::logCustom(
                    $group,
                    'reservation_added',
                    "Reservation {$reservation->reservation_code} added to group {$group->group_code} by {$performedBy->name}",
                    $performedBy->id,
                );
            }

            return $reservation;
        });
    }
}
