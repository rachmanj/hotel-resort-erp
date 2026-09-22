<?php

namespace App\Services;

use App\Enums\ProformaPaymentStatus;
use App\Models\ProformaInvoice;
use App\Models\ProformaPayment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProformaPaymentReceiptNumberService
{
    /**
     * Reserve the next receipt number for a proforma invoice, e.g. PR-045/PI/PRATA/VI/2026.
     *
     * The receipt carries the proforma invoice number so the paper trail stays paired;
     * a second and later receipt against the same invoice gets a -2, -3 suffix to keep
     * the unique index satisfied.
     *
     * Must be called inside the transaction that marks the payment as verified: the row
     * lock taken here is only held until that transaction commits, which is what stops
     * two simultaneous verifications from claiming the same sequence.
     *
     * @return array{sequence: int, number: string}
     */
    public function reserveNext(ProformaInvoice $invoice): array
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('Receipt numbers must be reserved inside a database transaction.');
        }

        ProformaInvoice::query()
            ->withoutGlobalScope('hotel')
            ->whereKey($invoice->id)
            ->lockForUpdate()
            ->first();

        $issuedCount = (int) ProformaPayment::query()
            ->withoutGlobalScope('hotel')
            ->where('proforma_invoice_id', $invoice->id)
            ->where('status', ProformaPaymentStatus::Verified->value)
            ->whereNotNull('receipt_number')
            ->lockForUpdate()
            ->count();

        $sequence = $issuedCount + 1;

        return [
            'sequence' => $sequence,
            'number' => $this->format($invoice->number, $sequence),
        ];
    }

    public function format(string $invoiceNumber, int $sequence): string
    {
        $number = 'PR-'.$invoiceNumber;

        return $sequence > 1 ? $number.'-'.$sequence : $number;
    }
}
