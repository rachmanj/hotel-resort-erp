<?php

namespace App\Listeners;

use App\Enums\PaymentMethod;
use App\Events\PaymentReceived;
use App\Models\ChartOfAccount;
use App\Services\Accounting\GlPostingService;

class PostPaymentToGl
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function handle(PaymentReceived $event): void
    {
        $payment = $event->payment->loadMissing('folio');
        $folio = $payment->folio;

        if ($folio === null || $payment->is_refund) {
            return;
        }

        $hotelId = (int) $folio->hotel_id;
        $amount = round((float) $payment->amount, 2);

        if ($amount <= 0) {
            return;
        }

        $transactionDate = ($payment->paid_at ?? now())->toDateString();
        $reference = $payment->reference_no ?? $folio->folio_no;
        $sourceType = 'payment';
        $sourceId = $payment->id;

        $guestLedger = $this->glPostingService->findAccountByCode($hotelId, '1-1300');
        $cashAccount = $this->resolveCashAccount($hotelId, $payment->method);

        $this->glPostingService->post([
            [
                'hotel_id' => $hotelId,
                'chart_of_account_id' => $cashAccount->id,
                'transaction_date' => $transactionDate,
                'debit' => $amount,
                'credit' => 0,
                'description' => "Payment received - {$folio->folio_no}",
                'reference_number' => $reference,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'hotel_id' => $hotelId,
                'chart_of_account_id' => $guestLedger->id,
                'transaction_date' => $transactionDate,
                'debit' => 0,
                'credit' => $amount,
                'description' => "Payment received - {$folio->folio_no}",
                'reference_number' => $reference,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
        ]);
    }

    private function resolveCashAccount(int $hotelId, PaymentMethod|string $method): ChartOfAccount
    {
        $methodValue = $method instanceof PaymentMethod ? $method->value : (string) $method;

        $code = match ($methodValue) {
            PaymentMethod::Cash->value, 'cash' => '1-1100',
            PaymentMethod::CityLedger->value, 'city_ledger' => '1-1400',
            default => '1-1200',
        };

        return $this->glPostingService->findAccountByCode($hotelId, $code);
    }
}
