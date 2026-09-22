<?php

namespace App\Actions\Billing;

use App\Enums\GuestInvoiceStatus;
use App\Models\GuestInvoice;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReleaseGuestInvoiceAction
{
    public function __invoke(GuestInvoice $invoice, User $releasedBy): GuestInvoice
    {
        if ($invoice->isReleased()) {
            throw new InvalidArgumentException("Invoice {$invoice->number} has already been released and cannot be released again.");
        }

        return DB::transaction(function () use ($invoice, $releasedBy): GuestInvoice {
            $releasedAt = now();

            $invoice->update([
                'status' => GuestInvoiceStatus::Released->value,
                'issued_at' => $releasedAt,
                'released_at' => $releasedAt,
                'released_by' => $releasedBy->id,
                'approved_by' => $releasedBy->id,
            ]);

            $invoice->loadMissing('folio');

            if ($invoice->folio !== null) {
                ActivityLogObserver::logCustom(
                    $invoice->folio,
                    'invoice_released',
                    "Invoice {$invoice->number} released by {$releasedBy->name}",
                    $releasedBy->id,
                );
            }

            return $invoice;
        });
    }
}
