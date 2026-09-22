<?php

namespace App\Actions\Reservations;

use App\Enums\ProformaPaymentStatus;
use App\Models\ProformaInvoice;
use App\Models\ProformaPayment;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use Illuminate\Support\Facades\DB;

class RecordProformaPaymentAction
{
    public function __construct(private RefreshProformaInvoiceTotalsAction $refreshTotals) {}

    /**
     * Log a down payment claimed by Marketing. No receipt is issued here and nothing is
     * posted to the folio: the folio does not exist until check in, and the money is only
     * counted as received once Finance verifies it.
     *
     * @param  array{amount: float|int|string, method: string, received_from: string, paid_at: string, reference_no?: string|null, proof_path?: string|null, notes?: string|null}  $data
     */
    public function __invoke(ProformaInvoice $invoice, array $data, User $recordedBy): ProformaPayment
    {
        return DB::transaction(function () use ($invoice, $data, $recordedBy): ProformaPayment {
            $payment = ProformaPayment::query()->create([
                'hotel_id' => $invoice->hotel_id,
                'proforma_invoice_id' => $invoice->id,
                'reservation_id' => $invoice->reservation_id,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'received_from' => $data['received_from'],
                'reference_no' => $data['reference_no'] ?? null,
                'proof_path' => $data['proof_path'] ?? null,
                'paid_at' => $data['paid_at'],
                'status' => ProformaPaymentStatus::Recorded->value,
                'recorded_by' => $recordedBy->id,
                'notes' => $data['notes'] ?? null,
            ]);

            ($this->refreshTotals)($invoice);

            $invoice->loadMissing('reservation');

            if ($invoice->reservation !== null) {
                ActivityLogObserver::logCustom(
                    $invoice->reservation,
                    'payment_recorded',
                    sprintf(
                        'Payment of Rp %s against %s recorded by %s',
                        number_format((float) $payment->amount, 0, ',', '.'),
                        $invoice->number,
                        $recordedBy->name,
                    ),
                    $recordedBy->id,
                );
            }

            return $payment;
        });
    }
}
