<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\Facades\DB;

class CancelReservationAction
{
    /**
     * @param  array{cancelled_reason?: string|null}  $data
     */
    public function __invoke(Reservation $reservation, array $data = [], ?User $performedBy = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $data, $performedBy): Reservation {
            $reason = $data['cancelled_reason'] ?? null;

            $reservation->update([
                'status' => ReservationStatus::Cancelled->value,
                'cancelled_reason' => $reason,
            ]);

            $roomIds = [];

            foreach ($reservation->reservationRooms as $reservationRoom) {
                $reservationRoom->update([
                    'status' => ReservationRoomStatus::Cancelled->value,
                ]);

                if ($reservationRoom->room_id !== null) {
                    $roomIds[] = $reservationRoom->room_id;
                }
            }

            foreach (array_unique($roomIds) as $roomId) {
                $room = Room::query()->find($roomId);
                if ($room !== null && $room->status === RoomStatus::Reserved) {
                    $room->update(['status' => RoomStatus::VacantClean->value]);
                }
            }

            $reservation = $reservation->load(['guest', 'reservationRooms.room', 'reservationRooms.roomType']);

            if ($performedBy !== null) {
                $reasonText = $reason ?? 'N/A';
                ActivityLogObserver::logCustom(
                    $reservation,
                    'cancelled',
                    "Reservation {$reservation->reservation_code} cancelled by {$performedBy->name}. Reason: {$reasonText}",
                    $performedBy->id,
                );
            }

            return $reservation;
        });
    }
}
