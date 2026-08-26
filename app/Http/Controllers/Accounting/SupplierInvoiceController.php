<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\SupplierInvoiceStatus;
use App\Enums\TaxTransactionStatus;
use App\Enums\TaxType;
use App\Events\SupplierInvoiceCreated;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Models\TaxTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplierInvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $invoices = SupplierInvoice::query()
            ->with('supplier:id,name')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('invoice_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SupplierInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'supplier_name' => $invoice->supplier?->name,
                'invoice_date' => $invoice->invoice_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'total_amount' => (float) $invoice->total_amount,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
            ]);

        return Inertia::render('Accounting/Payables/Index', [
            'invoices' => $invoices,
            'filters' => $request->only(['status']),
            'statusOptions' => collect(SupplierInvoiceStatus::cases())->map(fn (SupplierInvoiceStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Accounting/Payables/Create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'purchaseOrders' => PurchaseOrder::query()->orderByDesc('id')->limit(50)->get(['id', 'po_no', 'supplier_id']),
            'accounts' => ChartOfAccount::query()
                ->where('is_postable', true)
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'name']),
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_no' => ['required', 'string', 'max:50'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
            'withholding_tax_amount' => ['required', 'numeric', 'min:0'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.chart_of_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.department_id' => ['nullable', 'exists:departments,id'],
        ]);

        $invoice = DB::transaction(function () use ($validated): SupplierInvoice {
            $subtotal = collect($validated['lines'])->sum(fn (array $line): float => round($line['quantity'] * $line['unit_cost'], 2));
            $total = round($subtotal + $validated['tax_amount'] - $validated['withholding_tax_amount'], 2);

            $invoice = SupplierInvoice::query()->create([
                'invoice_no' => $validated['invoice_no'],
                'supplier_id' => $validated['supplier_id'],
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'subtotal' => $subtotal,
                'tax_amount' => $validated['tax_amount'],
                'withholding_tax_amount' => $validated['withholding_tax_amount'],
                'total_amount' => $total,
                'status' => SupplierInvoiceStatus::PendingApproval->value,
            ]);

            foreach ($validated['lines'] as $line) {
                $amount = round($line['quantity'] * $line['unit_cost'], 2);
                SupplierInvoiceLine::query()->create([
                    'supplier_invoice_id' => $invoice->id,
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'department_id' => $line['department_id'] ?? null,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'amount' => $amount,
                ]);
            }

            if ((float) $validated['tax_amount'] > 0) {
                TaxTransaction::query()->create([
                    'tax_type' => TaxType::PpnInput->value,
                    'source_type' => 'supplier_invoice',
                    'source_id' => $invoice->id,
                    'transaction_date' => $validated['invoice_date'],
                    'base_amount' => $subtotal,
                    'tax_rate_percent' => round($subtotal > 0 ? ($validated['tax_amount'] / $subtotal) * 100 : 0, 2),
                    'tax_amount' => $validated['tax_amount'],
                    'tax_period' => date('Y-m', strtotime($validated['invoice_date'])),
                    'status' => TaxTransactionStatus::Unreported->value,
                ]);
            }

            return $invoice;
        });

        return redirect()->route('accounting.payables.show', $invoice)->with('success', 'Supplier invoice created.');
    }

    public function show(SupplierInvoice $supplierInvoice): Response
    {
        $supplierInvoice->load(['supplier', 'purchaseOrder', 'lines.chartOfAccount', 'lines.department']);

        return Inertia::render('Accounting/Payables/Show', [
            'invoice' => [
                'id' => $supplierInvoice->id,
                'invoice_no' => $supplierInvoice->invoice_no,
                'supplier_name' => $supplierInvoice->supplier?->name,
                'purchase_order_no' => $supplierInvoice->purchaseOrder?->po_no,
                'invoice_date' => $supplierInvoice->invoice_date->toDateString(),
                'due_date' => $supplierInvoice->due_date->toDateString(),
                'subtotal' => (float) $supplierInvoice->subtotal,
                'tax_amount' => (float) $supplierInvoice->tax_amount,
                'withholding_tax_amount' => (float) $supplierInvoice->withholding_tax_amount,
                'total_amount' => (float) $supplierInvoice->total_amount,
                'status' => $supplierInvoice->status->value,
                'status_label' => $supplierInvoice->status->label(),
                'lines' => $supplierInvoice->lines->map(fn (SupplierInvoiceLine $line) => [
                    'description' => $line->description,
                    'account_code' => $line->chartOfAccount?->account_code,
                    'account_name' => $line->chartOfAccount?->name,
                    'department_name' => $line->department?->name,
                    'quantity' => (float) $line->quantity,
                    'unit_cost' => (float) $line->unit_cost,
                    'amount' => (float) $line->amount,
                ]),
            ],
        ]);
    }

    public function approve(SupplierInvoice $supplierInvoice): RedirectResponse
    {
        if ($supplierInvoice->status !== SupplierInvoiceStatus::PendingApproval) {
            return back()->with('error', 'Only pending invoices can be approved.');
        }

        $supplierInvoice->update(['status' => SupplierInvoiceStatus::Approved->value]);

        SupplierInvoiceCreated::dispatch(
            (int) $supplierInvoice->hotel_id,
            $supplierInvoice->id,
            (float) $supplierInvoice->total_amount,
            "Supplier invoice {$supplierInvoice->invoice_no}",
        );

        return back()->with('success', 'Supplier invoice approved and posted to GL.');
    }
}
