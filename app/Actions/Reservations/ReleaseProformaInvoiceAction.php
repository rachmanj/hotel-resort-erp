<?php

namespace App\Actions\Reservations;

use App\Enums\ProformaInvoiceStatus;
use App\Models\ProformaInvoice;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReleaseProformaInvoiceAction
{
    public function __invoke(ProformaInvoice $invoice, User $releasedBy): ProformaInvoice
    {
        if ($invoice->isReleased()) {
            throw new InvalidArgumentException('Proforma invoice is already released.');
        }

        return DB::transaction(function () use ($invoice, $releasedBy): ProformaInvoice {
            $releasedAt = now();

            $invoice->update([
                'status' => ProformaInvoiceStatus::Released->value,
                'issued_at' => $releasedAt,
                'released_at' => $releasedAt,
                'released_by' => $releasedBy->id,
            ]);

            $invoice->loadMissing('reservation');

            if ($invoice->reservation !== null) {
                ActivityLogObserver::logCustom(
                    $invoice->reservation,
                    'proforma_released',
                    "Proforma invoice {$invoice->number} released by {$releasedBy->name}",
                    $releasedBy->id,
                );
            }

            return $invoice;
        });
    }
}
