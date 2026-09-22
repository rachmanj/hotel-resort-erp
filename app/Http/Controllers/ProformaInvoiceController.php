<?php

namespace App\Http\Controllers;

use App\Actions\Reservations\ReleaseProformaInvoiceAction;
use App\Actions\Reservations\SyncProformaInvoiceAction;
use App\Models\ProformaInvoice;
use App\Models\ProformaInvoiceLine;
use App\Models\Reservation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

class ProformaInvoiceController extends Controller
{
    public function show(Request $request, Reservation $reservation, SyncProformaInvoiceAction $syncProformaInvoice): InertiaResponse
    {
        $invoice = $this->currentInvoice($reservation, $syncProformaInvoice);

        return Inertia::render('Reservations/Proforma', [
            ...$this->buildDocumentData($reservation, $invoice),
            'canRelease' => $request->user()?->can('proforma.release') ?? false,
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

    /**
     * Reservations booked before this module existed have no document yet, so the
     * latest revision is created on demand.
     */
    private function currentInvoice(Reservation $reservation, SyncProformaInvoiceAction $syncProformaInvoice): ProformaInvoice
    {
        $invoice = ProformaInvoice::query()
            ->where('reservation_id', $reservation->id)
            ->orderByDesc('revision')
            ->first();

        return $invoice ?? $syncProformaInvoice($reservation);
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
}
