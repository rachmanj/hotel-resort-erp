<?php

namespace App\Http\Controllers;

use App\Actions\Reservations\RecordProformaPaymentAction;
use App\Actions\Reservations\SyncProformaInvoiceAction;
use App\Actions\Reservations\VerifyProformaPaymentAction;
use App\Enums\ProformaPaymentMethod;
use App\Http\Requests\StoreProformaPaymentRequest;
use App\Http\Requests\VerifyProformaPaymentRequest;
use App\Models\ProformaPayment;
use App\Models\Reservation;
use App\Services\IndonesianNumberWordsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProformaPaymentController extends Controller
{
    public function store(
        StoreProformaPaymentRequest $request,
        Reservation $reservation,
        SyncProformaInvoiceAction $syncProformaInvoice,
        RecordProformaPaymentAction $recordProformaPayment,
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return back()->with('error', 'Unable to resolve the recording user.');
        }

        $invoice = $syncProformaInvoice->current($reservation);
        $data = $request->safe()->except('proof');

        if ($request->hasFile('proof')) {
            $data['proof_path'] = $request->file('proof')->store('proforma-payments/'.$invoice->id, 'public');
        }

        $recordProformaPayment($invoice, $data, $user);

        return back()->with('success', 'Payment recorded and waiting for finance verification.');
    }

    public function verify(
        VerifyProformaPaymentRequest $request,
        ProformaPayment $proformaPayment,
        VerifyProformaPaymentAction $verifyProformaPayment,
    ): RedirectResponse {
        $user = $request->user();

        if ($user === null) {
            return back()->with('error', 'Unable to resolve the verifying user.');
        }

        try {
            $payment = $verifyProformaPayment($proformaPayment, $user);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Payment verified, receipt {$payment->receipt_number} issued.");
    }

    public function receipt(ProformaPayment $proformaPayment, IndonesianNumberWordsService $numberWords): Response
    {
        if (! $proformaPayment->isVerified()) {
            throw new NotFoundHttpException('Receipt is only available once the payment is verified.');
        }

        $pdf = Pdf::loadView('receipts.payment', $this->buildReceiptData($proformaPayment, $numberWords));

        $filename = 'receipt-'.str_replace('/', '-', (string) $proformaPayment->receipt_number).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReceiptData(ProformaPayment $payment, IndonesianNumberWordsService $numberWords): array
    {
        $payment->loadMissing(['proformaInvoice', 'reservation.guest']);

        $invoice = $payment->proformaInvoice;
        $amount = (float) $payment->amount;

        return [
            'company' => [
                'name' => config('proforma.company_name'),
            ],
            'receipt' => [
                'number' => $payment->receipt_number,
                'date' => ($payment->receipt_issued_at ?? $payment->created_at)->format('d M Y'),
                'received_from' => $payment->received_from,
                'amount' => $amount,
                'amount_in_words' => $numberWords->rupiah($amount),
                'in_payment_of' => $payment->notes
                    ?? 'Pembayaran Proforma Invoice '.$invoice->number.' / '.$payment->reservation?->reservation_code,
                'reference_no' => $payment->reference_no,
                'is_cash' => $payment->method === ProformaPaymentMethod::Cash,
                'is_transfer' => $payment->method === ProformaPaymentMethod::BankTransfer,
            ],
            'proforma' => [
                'number' => $invoice->number,
                'total' => (float) $invoice->total,
                'outstanding_total' => (float) $invoice->outstanding_total,
            ],
        ];
    }
}
