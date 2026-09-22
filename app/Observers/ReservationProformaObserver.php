<?php

namespace App\Observers;

use App\Actions\Reservations\SyncProformaInvoiceAction;
use App\Models\Reservation;

class ReservationProformaObserver
{
    public function __construct(
        private SyncProformaInvoiceAction $syncProformaInvoice,
    ) {}

    public function created(Reservation $reservation): void
    {
        ($this->syncProformaInvoice)($reservation);
    }

    public function updated(Reservation $reservation): void
    {
        if (! $reservation->wasChanged(['arrival_date', 'departure_date'])) {
            return;
        }

        ($this->syncProformaInvoice)($reservation);
    }
}
