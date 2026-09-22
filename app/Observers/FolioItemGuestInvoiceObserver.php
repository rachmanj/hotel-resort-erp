<?php

namespace App\Observers;

use App\Actions\Billing\SyncGuestInvoiceAction;
use App\Models\FolioItem;

class FolioItemGuestInvoiceObserver
{
    public function __construct(
        private SyncGuestInvoiceAction $syncGuestInvoice,
    ) {}

    public function created(FolioItem $folioItem): void
    {
        $this->sync($folioItem);
    }

    public function updated(FolioItem $folioItem): void
    {
        if (! $folioItem->wasChanged(['description', 'quantity', 'unit_price', 'amount', 'tax_amount', 'service_charge_amount'])) {
            return;
        }

        $this->sync($folioItem);
    }

    public function deleted(FolioItem $folioItem): void
    {
        $this->sync($folioItem);
    }

    private function sync(FolioItem $folioItem): void
    {
        $folio = $folioItem->folio()->withoutGlobalScope('hotel')->first();

        if ($folio === null) {
            return;
        }

        ($this->syncGuestInvoice)($folio);
    }
}
