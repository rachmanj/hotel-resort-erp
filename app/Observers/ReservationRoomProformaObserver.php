<?php

namespace App\Observers;

use App\Actions\Reservations\SyncProformaInvoiceAction;
use App\Models\ReservationRoom;

class ReservationRoomProformaObserver
{
    public function __construct(
        private SyncProformaInvoiceAction $syncProformaInvoice,
    ) {}

    public function created(ReservationRoom $reservationRoom): void
    {
        $this->sync($reservationRoom);
    }

    public function updated(ReservationRoom $reservationRoom): void
    {
        if (! $reservationRoom->wasChanged(['room_type_id', 'nightly_rate'])) {
            return;
        }

        $this->sync($reservationRoom);
    }

    public function deleted(ReservationRoom $reservationRoom): void
    {
        $this->sync($reservationRoom);
    }

    private function sync(ReservationRoom $reservationRoom): void
    {
        $reservation = $reservationRoom->reservation()->withoutGlobalScope('hotel')->first();

        if ($reservation === null) {
            return;
        }

        ($this->syncProformaInvoice)($reservation);
    }
}
