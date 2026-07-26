<?php

namespace App\Http\Controllers;

use App\Models\Folio;
use App\Services\FolioPostingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InvoiceController extends Controller
{
    public function show(Folio $folio, FolioPostingService $folioPostingService): InertiaResponse
    {
        $data = $this->buildInvoiceData($folio, $folioPostingService);

        return Inertia::render('Folios/Invoice', $data);
    }

    public function download(Folio $folio, FolioPostingService $folioPostingService): Response
    {
        $data = $this->buildInvoiceData($folio, $folioPostingService);

        $pdf = Pdf::loadView('invoices.folio', $data);

        return $pdf->download("invoice-{$folio->folio_no}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInvoiceData(Folio $folio, FolioPostingService $folioPostingService): array
    {
        $folio->load(['guest', 'reservation', 'company', 'items', 'payments']);

        return [
            'folio' => [
                'id' => $folio->id,
                'folio_no' => $folio->folio_no,
                'status' => $folio->status->value,
                'opened_at' => $folio->opened_at?->format('d M Y H:i'),
                'closed_at' => $folio->closed_at?->format('d M Y H:i'),
                'guest' => $folio->guest?->only(['full_name', 'phone', 'email', 'address']),
                'reservation' => $folio->reservation?->only(['reservation_code', 'arrival_date', 'departure_date']),
                'company' => $folio->company?->only(['name', 'tax_id', 'billing_address']),
                'items' => $folio->items->map(fn ($item) => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'amount' => $item->amount,
                    'tax_amount' => $item->tax_amount,
                    'service_charge_amount' => $item->service_charge_amount,
                    'line_total' => $item->line_total,
                ]),
                'payments' => $folio->payments->map(fn ($payment) => [
                    'amount' => $payment->amount,
                    'method' => $payment->method->label(),
                    'reference_no' => $payment->reference_no,
                    'paid_at' => $payment->paid_at?->format('d M Y H:i'),
                ]),
            ],
            'balance' => $folioPostingService->getBalance($folio),
            'charges_total' => $folioPostingService->getChargesTotal($folio),
        ];
    }
}
