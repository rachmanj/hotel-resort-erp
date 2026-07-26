<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\ArInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\ArInvoice;
use App\Services\FolioPostingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArInvoiceController extends Controller
{
    public function __construct(
        private FolioPostingService $folioPostingService,
    ) {}

    public function index(Request $request): Response
    {
        $invoices = ArInvoice::query()
            ->with('company:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('issued_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ArInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'company_name' => $invoice->company?->name,
                'period_start' => $invoice->period_start->toDateString(),
                'period_end' => $invoice->period_end->toDateString(),
                'total_amount' => (float) $invoice->total_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'balance_due' => $invoice->balanceDue(),
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'due_date' => $invoice->due_date->toDateString(),
                'issued_at' => $invoice->issued_at->toDateTimeString(),
            ]);

        return Inertia::render('Accounting/Receivables/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['status']),
            'statusOptions' => collect(ArInvoiceStatus::cases())->map(fn (ArInvoiceStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function show(ArInvoice $arInvoice): Response
    {
        $arInvoice->load(['company', 'folios.guest']);

        return Inertia::render('Accounting/Receivables/Show', [
            'invoice' => [
                'id' => $arInvoice->id,
                'invoice_no' => $arInvoice->invoice_no,
                'company_name' => $arInvoice->company?->name,
                'period_start' => $arInvoice->period_start->toDateString(),
                'period_end' => $arInvoice->period_end->toDateString(),
                'total_amount' => (float) $arInvoice->total_amount,
                'paid_amount' => (float) $arInvoice->paid_amount,
                'balance_due' => $arInvoice->balanceDue(),
                'original_currency_code' => $arInvoice->original_currency_code,
                'original_amount' => $arInvoice->original_amount !== null ? (float) $arInvoice->original_amount : null,
                'status' => $arInvoice->status->value,
                'status_label' => $arInvoice->status->label(),
                'due_date' => $arInvoice->due_date->toDateString(),
                'issued_at' => $arInvoice->issued_at->toDateTimeString(),
                'folios' => $arInvoice->folios->map(fn ($folio) => [
                    'id' => $folio->id,
                    'folio_no' => $folio->folio_no,
                    'guest_name' => $folio->guest?->full_name,
                    'balance' => $this->folioPostingService->getBalance($folio),
                ]),
            ],
        ]);
    }
}
