<?php

namespace App\Actions\Reservations;

use App\Enums\ProformaPaymentStatus;
use App\Models\ProformaInvoice;
use App\Models\ProformaPayment;

class RefreshProformaInvoiceTotalsAction
{
    /**
     * Only verified payments count as received: money is outstanding until Finance
     * confirms it landed in the bank account.
     */
    public function __invoke(ProformaInvoice $invoice): ProformaInvoice
    {
        $received = (float) ProformaPayment::query()
            ->withoutGlobalScope('hotel')
            ->where('proforma_invoice_id', $invoice->id)
            ->where('status', ProformaPaymentStatus::Verified->value)
            ->sum('amount');

        $invoice->update([
            'received_total' => round($received, 2),
            'outstanding_total' => round((float) $invoice->total - $received, 2),
        ]);

        return $invoice;
    }
}
