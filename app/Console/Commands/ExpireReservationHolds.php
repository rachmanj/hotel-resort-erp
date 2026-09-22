<?php

namespace App\Console\Commands;

use App\Actions\Reservations\CancelReservationAction;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireReservationHolds extends Command
{
    protected $signature = 'reservations:expire-holds';

    protected $description = 'Cancel tentative reservations whose hold limit has passed';

    public function handle(CancelReservationAction $cancelReservation): int
    {
        $expired = Reservation::query()
            ->withoutGlobalScope('hotel')
            ->with('reservationRooms')
            ->where('status', ReservationStatus::Tentative->value)
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired reservation holds.');

            return self::SUCCESS;
        }

        foreach ($expired as $reservation) {
            $expiredAt = $reservation->hold_expires_at->toDateTimeString();

            $cancelReservation($reservation, [
                'cancelled_reason' => "Hold expired on {$expiredAt} without confirmation.",
            ]);

            Log::info('Reservation hold expired and cancelled', [
                'reservation_id' => $reservation->id,
                'reservation_code' => $reservation->reservation_code,
                'hotel_id' => $reservation->hotel_id,
                'hold_expires_at' => $expiredAt,
            ]);

            $this->info("Cancelled {$reservation->reservation_code} (hold expired {$expiredAt}).");
        }

        $this->info("Expired {$expired->count()} reservation hold(s).");

        return self::SUCCESS;
    }
}
