<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConfirmReservationAction
{
    /**
     * Turns a held booking into a firm one: the hold limit is dropped so the
     * expiry command can no longer cancel it.
     */
    public function __invoke(Reservation $reservation, ?User $performedBy = null): Reservation
    {
        if ($reservation->status !== ReservationStatus::Tentative) {
            throw new InvalidArgumentException(
                "Only tentative reservations can be confirmed, {$reservation->reservation_code} is {$reservation->status->label()}."
            );
        }

        return DB::transaction(function () use ($reservation, $performedBy): Reservation {
            $reservation->update([
                'status' => ReservationStatus::Confirmed->value,
                'hold_expires_at' => null,
            ]);

            if ($performedBy !== null) {
                ActivityLogObserver::logCustom(
                    $reservation,
                    'confirmed',
                    "Reservation {$reservation->reservation_code} confirmed by {$performedBy->name}",
                    $performedBy->id,
                );
            }

            return $reservation->fresh(['guest', 'reservationRooms.room', 'reservationRooms.roomType']);
        });
    }
}
