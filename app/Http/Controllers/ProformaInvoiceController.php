<?php

namespace App\Http\Controllers;

use App\Actions\Reservations\ReleaseProformaInvoiceAction;
use App\Actions\Reservations\SyncProformaInvoiceAction;
use App\Models\ProformaInvoice;
use App\Models\ProformaInvoiceLine;
use App\Models\ProformaPayment;
use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

class ProformaInvoiceController extends Controller
{
    public function show(Request $request, Reservation $reservation, SyncProformaInvoiceAction $syncProformaInvoice): InertiaResponse
    {
        $invoice = $this->currentInvoice($reservation, $syncProformaInvoice);

        $user = $request->user();

        return Inertia::render('Reservations/Proforma', [
            ...$this->buildDocumentData($reservation, $invoice),
            'payments' => $this->buildPayments($invoice),
            'canRelease' => $user?->can('proforma.release') ?? false,
            'canRecordPayment' => $user?->can('proforma.payment.record') ?? false,
            'canVerifyPayment' => $user?->can('proforma.payment.verify') ?? false,
        ]);
    }

    public function download(Reservation $reservation, SyncProformaInvoiceAction $syncProformaInvoice): Response
    {
        $invoice = $this->currentInvoice($reservation, $syncProformaInvoice);

        $pdf = Pdf::loadView('invoices.proforma', $this->buildDocumentData($reservation, $invoice));

        $filename = 'proforma-'.str_replace('/', '-', $invoice->number).'.pdf';

        return $pdf->download($filename);
    }

    public function release(
        Request $request,
        Reservation $reservation,
        SyncProformaInvoiceAction $syncProformaInvoice,
        ReleaseProformaInvoiceAction $releaseProformaInvoice,
    ): RedirectResponse {
        $invoice = $this->currentInvoice($reservation, $syncProformaInvoice);
        $user = $request->user();

        if ($user === null) {
            return back()->with('error', 'Unable to resolve the releasing user.');
        }

        try {
            $releaseProformaInvoice($invoice, $user);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Proforma invoice {$invoice->number} released.");
    }

    private function currentInvoice(Reservation $reservation, SyncProformaInvoiceAction $syncProformaInvoice): ProformaInvoice
    {
        return $syncProformaInvoice->current($reservation);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDocumentData(Reservation $reservation, ProformaInvoice $invoice): array
    {
        $reservation->loadMissing('guest');
        $invoice->loadMissing(['lines', 'releasedBy:id,name']);

        $arrival = $reservation->arrival_date;
        $departure = $reservation->departure_date;

        return [
            'company' => [
                'name' => config('proforma.company_name'),
                'title' => 'Proforma Invoice',
            ],
            'reservation' => [
                'id' => $reservation->id,
                'reservation_code' => $reservation->reservation_code,
            ],
            'customer' => [
                'id' => $reservation->guest_id,
                'name' => $reservation->guest?->full_name,
                'contact' => $reservation->guest?->phone ?? $reservation->guest?->email,
            ],
            'proforma' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'status_color' => $invoice->status->color(),
                'revision' => $invoice->revision,
                'date' => ($invoice->issued_at ?? $invoice->created_at)->format('d M Y'),
                'date_of_stay' => $arrival->format('d M Y').' - '.$departure->format('d M Y'),
                'nights' => max(1, (int) $arrival->diffInDays($departure)),
                'issued_at' => $invoice->issued_at?->format('d M Y H:i'),
                'released_at' => $invoice->released_at?->format('d M Y H:i'),
                'released_by' => $invoice->releasedBy?->name,
                'prepared_by' => $invoice->prepared_by,
                'notes' => $invoice->notes,
                'subtotal' => (float) $invoice->subtotal,
                'total' => (float) $invoice->total,
                'received_total' => (float) $invoice->received_total,
                'outstanding_total' => (float) $invoice->outstanding_total,
                'lines' => $invoice->lines->map(fn (ProformaInvoiceLine $line) => [
                    'id' => $line->id,
                    'description' => $line->description,
                    'note' => $line->note,
                    'unit_price' => (float) $line->unit_price,
                    'quantity' => $line->quantity,
                    'nights' => $line->nights,
                    'stay_price' => $line->stayPrice(),
                    'amount' => (float) $line->amount,
                ])->all(),
            ],
            'payment_details' => [
                'bank_accounts' => config('proforma.bank_accounts'),
                'terms' => config('proforma.payment_terms'),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildPayments(ProformaInvoice $invoice): array
    {
        return $invoice->payments()
            ->with(['recordedBy:id,name', 'verifiedBy:id,name'])
            ->get()
            ->map(fn (ProformaPayment $payment) => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'method' => $payment->method->value,
                'method_label' => $payment->method->label(),
                'received_from' => $payment->received_from,
                'reference_no' => $payment->reference_no,
                'paid_at' => $payment->paid_at->format('d M Y'),
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'status_color' => $payment->status->color(),
                'recorded_by' => $payment->recordedBy?->name,
                'verified_by' => $payment->verifiedBy?->name,
                'verified_at' => $payment->verified_at?->format('d M Y H:i'),
                'receipt_number' => $payment->receipt_number,
                'proof_url' => $payment->proof_path === null ? null : Storage::disk('public')->url($payment->proof_path),
                'notes' => $payment->notes,
            ])
            ->all();
    }
}
