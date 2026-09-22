<?php

namespace App\Actions\Reservations;

use App\Enums\ProformaPaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\ProformaPayment;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\ProformaPaymentReceiptNumberService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VerifyProformaPaymentAction
{
    public function __construct(
        private ProformaPaymentReceiptNumberService $receiptNumberService,
        private RefreshProformaInvoiceTotalsAction $refreshTotals,
    ) {}

    /**
     * Finance confirms the money reached the bank account: the receipt number is issued,
     * the outstanding amount drops and a tentative reservation becomes confirmed.
     */
    public function __invoke(ProformaPayment $payment, User $verifiedBy): ProformaPayment
    {
        if ($payment->isVerified()) {
            throw new InvalidArgumentException("Payment already verified, receipt {$payment->receipt_number} was issued.");
        }

        return DB::transaction(function () use ($payment, $verifiedBy): ProformaPayment {
            $invoice = $payment->proformaInvoice()->withoutGlobalScope('hotel')->firstOrFail();
            $receipt = $this->receiptNumberService->reserveNext($invoice);
            $verifiedAt = now();

            $payment->update([
                'status' => ProformaPaymentStatus::Verified->value,
                'verified_by' => $verifiedBy->id,
                'verified_at' => $verifiedAt,
                'receipt_sequence' => $receipt['sequence'],
                'receipt_number' => $receipt['number'],
                'receipt_issued_at' => $verifiedAt,
            ]);

            ($this->refreshTotals)($invoice);

            $reservation = $payment->reservation()->withoutGlobalScope('hotel')->first();

            if ($reservation !== null) {
                if ($reservation->status === ReservationStatus::Tentative) {
                    $reservation->update(['status' => ReservationStatus::Confirmed->value]);
                }

                ActivityLogObserver::logCustom(
                    $reservation,
                    'payment_verified',
                    sprintf(
                        'Payment of Rp %s verified by %s, receipt %s issued',
                        number_format((float) $payment->amount, 0, ',', '.'),
                        $verifiedBy->name,
                        $receipt['number'],
                    ),
                    $verifiedBy->id,
                );
            }

            return $payment;
        });
    }
}
