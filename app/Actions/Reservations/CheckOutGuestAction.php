<?php

namespace App\Actions\Reservations;

use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Folio;
use App\Models\GuestStay;
use App\Models\ReservationRoom;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\FolioPostingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CheckOutGuestAction
{
    public function __construct(
        private FolioPostingService $folioPostingService,
    ) {}

    public function __invoke(ReservationRoom $reservationRoom, ?User $performedBy = null): array
    {
        return DB::transaction(function () use ($reservationRoom, $performedBy): array {
            $reservationRoom = ReservationRoom::query()->lockForUpdate()->findOrFail($reservationRoom->id);
            $reservationRoom->load(['reservation.guest', 'room']);

            $reservation = $reservationRoom->reservation;

            if ($reservation === null) {
                throw new InvalidArgumentException('Reservation not found.');
            }

            if ($reservation->status !== ReservationStatus::CheckedIn) {
                throw new InvalidArgumentException('Reservation must be checked in to check out.');
            }

            if ($reservationRoom->status !== ReservationRoomStatus::CheckedIn) {
                throw new InvalidArgumentException('Room is not checked in.');
            }

            $folio = Folio::query()
                ->where('reservation_id', $reservation->id)
                ->where('type', 'master')
                ->first();

            $balance = $folio !== null ? $this->folioPostingService->getBalance($folio) : 0.0;
            $totalSpend = $folio !== null ? $this->folioPostingService->getChargesTotal($folio) : 0.0;

            $checkInAt = $reservationRoom->check_in_at ?? now();
            $checkOutAt = now();
            $nights = max(1, Carbon::parse($reservation->arrival_date)->diffInDays($reservation->departure_date));

            $reservationRoom->update([
                'status' => ReservationRoomStatus::CheckedOut->value,
                'check_out_at' => $checkOutAt,
            ]);

            if ($reservationRoom->room !== null) {
                $reservationRoom->room->update([
                    'status' => RoomStatus::VacantDirty->value,
                ]);
            }

            $allCheckedOut = $reservation->reservationRooms()
                ->where('status', '!=', ReservationRoomStatus::CheckedOut->value)
                ->doesntExist();

            if ($allCheckedOut) {
                $reservation->update([
                    'status' => ReservationStatus::CheckedOut->value,
                ]);
            }

            GuestStay::query()->create([
                'guest_id' => $reservation->guest_id,
                'reservation_id' => $reservation->id,
                'room_id' => $reservationRoom->room_id,
                'check_in_at' => $checkInAt,
                'check_out_at' => $checkOutAt,
                'nights' => $nights,
                'total_spend' => $totalSpend,
            ]);

            if ($folio !== null && $folio->company_id === null) {
                $this->folioPostingService->closeFolio($folio);
            }

            $reservation = $reservation->fresh(['guest', 'reservationRooms.room']);

            if ($performedBy !== null) {
                $roomNumber = $reservationRoom->room?->number ?? 'N/A';
                ActivityLogObserver::logCustom(
                    $reservation,
                    'checked_out',
                    "Reservation {$reservation->reservation_code} room {$roomNumber} checked out by {$performedBy->name}",
                    $performedBy->id,
                );
            }

            return [
                'reservation' => $reservation,
                'folio' => $folio?->fresh(),
                'balance' => $balance,
                'total_spend' => $totalSpend,
            ];
        });
    }
}
