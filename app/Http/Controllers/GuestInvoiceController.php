<?php

namespace App\Http\Controllers;

use App\Actions\Billing\ReleaseGuestInvoiceAction;
use App\Actions\Billing\SyncGuestInvoiceAction;
use App\Models\Folio;
use App\Models\GuestInvoice;
use App\Models\GuestInvoiceLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use InvalidArgumentException;

class GuestInvoiceController extends Controller
{
    public function show(Request $request, Folio $folio, SyncGuestInvoiceAction $syncGuestInvoice): InertiaResponse
    {
        $invoice = $syncGuestInvoice->current($folio);

        return Inertia::render('Folios/GuestInvoice', [
            ...$this->buildDocumentData($folio, $invoice),
            'canRelease' => $request->user()?->can('invoice.release') ?? false,
        ]);
    }

    public function download(Folio $folio, SyncGuestInvoiceAction $syncGuestInvoice): Response
    {
        $invoice = $syncGuestInvoice->current($folio);

        $pdf = Pdf::loadView('invoices.guest', $this->buildDocumentData($folio, $invoice));

        return $pdf->download("invoice-{$invoice->number}.pdf");
    }

    public function release(
        Request $request,
        Folio $folio,
        SyncGuestInvoiceAction $syncGuestInvoice,
        ReleaseGuestInvoiceAction $releaseGuestInvoice,
    ): RedirectResponse {
        $invoice = $syncGuestInvoice->current($folio);
        $user = $request->user();

        if ($user === null) {
            return back()->with('error', 'Unable to resolve the releasing user.');
        }

        try {
            $releaseGuestInvoice($invoice, $user);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Invoice {$invoice->number} released.");
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDocumentData(Folio $folio, GuestInvoice $invoice): array
    {
        $folio->loadMissing(['guest', 'reservation', 'company']);
        $invoice->loadMissing(['lines', 'preparedBy:id,name', 'approvedBy:id,name', 'releasedBy:id,name']);

        return [
            'company' => [
                'name' => config('invoice.company_name'),
                'title' => config('invoice.title'),
            ],
            'folio' => [
                'id' => $folio->id,
                'folio_no' => $folio->folio_no,
            ],
            'customer' => [
                'id' => $folio->guest_id,
                'name' => $folio->company?->name ?? $folio->guest?->full_name,
                'contact' => $folio->guest?->phone ?? $folio->guest?->email,
            ],
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'status_color' => $invoice->status->color(),
                'revision' => $invoice->revision,
                'date' => ($invoice->issued_at ?? $invoice->created_at)->format('d M Y'),
                'issued_at' => $invoice->issued_at?->format('d M Y H:i'),
                'released_at' => $invoice->released_at?->format('d M Y H:i'),
                'released_by' => $invoice->releasedBy?->name,
                'prepared_by' => $invoice->preparedBy?->name,
                'approved_by' => $invoice->approvedBy?->name,
                'notes' => $invoice->notes,
                'subtotal' => (float) $invoice->subtotal,
                'total' => (float) $invoice->total,
                'lines' => $invoice->lines->map(fn (GuestInvoiceLine $line) => [
                    'id' => $line->id,
                    'description' => $line->description,
                    'quantity' => (float) $line->quantity,
                    'nights' => $line->nights,
                    'unit_price' => (float) $line->unit_price,
                    'amount' => (float) $line->amount,
                    'line_total' => (float) $line->line_total,
                ])->all(),
            ],
            'terms' => [
                'title' => config('invoice.terms_title'),
                'bank_accounts' => config('invoice.bank_accounts'),
                'items' => config('invoice.terms'),
            ],
            'signatures' => config('invoice.signatures'),
        ];
    }
}
