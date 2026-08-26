<?php

namespace App\Listeners;

use App\Events\SupplierInvoiceCreated;
use App\Models\SupplierInvoice;
use App\Services\Accounting\GlPostingService;

class PostSupplierInvoiceToGl
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function handle(SupplierInvoiceCreated $event): void
    {
        $invoice = SupplierInvoice::query()
            ->withoutGlobalScope('hotel')
            ->with('lines')
            ->findOrFail($event->supplierInvoiceId);

        if ($invoice->lines->isEmpty()) {
            return;
        }

        $glLines = [];
        $hotelId = $event->hotelId;

        foreach ($invoice->lines as $line) {
            $glLines[] = [
                'hotel_id' => $hotelId,
                'chart_of_account_id' => $line->chart_of_account_id,
                'department_id' => $line->department_id,
                'transaction_date' => $invoice->invoice_date->toDateString(),
                'debit' => (float) $line->amount,
                'credit' => 0,
                'description' => $line->description,
                'reference_number' => $invoice->invoice_no,
                'source_type' => 'supplier_invoice',
                'source_id' => $invoice->id,
            ];
        }

        if ((float) $invoice->tax_amount > 0) {
            $ppnInput = $this->glPostingService->findAccountByCode($hotelId, '1-1700');
            $glLines[] = [
                'hotel_id' => $hotelId,
                'chart_of_account_id' => $ppnInput->id,
                'transaction_date' => $invoice->invoice_date->toDateString(),
                'debit' => (float) $invoice->tax_amount,
                'credit' => 0,
                'description' => "PPN Input - {$invoice->invoice_no}",
                'reference_number' => $invoice->invoice_no,
                'source_type' => 'supplier_invoice',
                'source_id' => $invoice->id,
            ];
        }

        $apAccount = $this->glPostingService->findAccountByCode($hotelId, '2-1100');
        $glLines[] = [
            'hotel_id' => $hotelId,
            'chart_of_account_id' => $apAccount->id,
            'transaction_date' => $invoice->invoice_date->toDateString(),
            'debit' => 0,
            'credit' => (float) $invoice->total_amount,
            'description' => "AP - {$invoice->invoice_no}",
            'reference_number' => $invoice->invoice_no,
            'source_type' => 'supplier_invoice',
            'source_id' => $invoice->id,
        ];

        if ((float) $invoice->withholding_tax_amount > 0) {
            $pph23 = $this->glPostingService->findAccountByCode($hotelId, '2-2200');
            $glLines[] = [
                'hotel_id' => $hotelId,
                'chart_of_account_id' => $pph23->id,
                'transaction_date' => $invoice->invoice_date->toDateString(),
                'debit' => 0,
                'credit' => (float) $invoice->withholding_tax_amount,
                'description' => "PPh 23 - {$invoice->invoice_no}",
                'reference_number' => $invoice->invoice_no,
                'source_type' => 'supplier_invoice',
                'source_id' => $invoice->id,
            ];
        }

        $this->glPostingService->post($glLines);
    }
}
