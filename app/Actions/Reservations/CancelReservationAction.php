<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Reservation;
use App\Models\Room;
use Illuminate\Support\Facades\DB;

class CancelReservationAction
{
    /**
     * @param  array{cancelled_reason?: string|null}  $data
     */
    public function __invoke(Reservation $reservation, array $data = []): Reservation
    {
        return DB::transaction(function () use ($reservation, $data): Reservation {
            $reservation->update([
                'status' => ReservationStatus::Cancelled->value,
                'cancelled_reason' => $data['cancelled_reason'] ?? null,
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

            return $reservation->load(['guest', 'reservationRooms.room', 'reservationRooms.roomType']);
        });
    }
}
