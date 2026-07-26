<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\TaxTransactionStatus;
use App\Enums\TaxType;
use App\Http\Controllers\Controller;
use App\Models\TaxTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaxReportController extends Controller
{
    public function index(Request $request): Response
    {
        $period = $request->string('period')->toString() ?: now()->format('Y-m');
        $taxType = $request->string('tax_type')->toString();

        $query = TaxTransaction::query()
            ->where('tax_period', $period)
            ->orderBy('transaction_date');

        if ($taxType !== '') {
            $query->where('tax_type', $taxType);
        }

        $transactions = $query->get()->map(fn (TaxTransaction $tx) => [
            'id' => $tx->id,
            'tax_type' => $tx->tax_type->value,
            'tax_type_label' => $tx->tax_type->label(),
            'source_type' => $tx->source_type,
            'source_id' => $tx->source_id,
            'transaction_date' => $tx->transaction_date->toDateString(),
            'base_amount' => (float) $tx->base_amount,
            'tax_rate_percent' => (float) $tx->tax_rate_percent,
            'tax_amount' => (float) $tx->tax_amount,
            'tax_period' => $tx->tax_period,
            'status' => $tx->status->value,
            'status_label' => $tx->status->label(),
        ]);

        $summary = TaxTransaction::query()
            ->where('tax_period', $period)
            ->selectRaw('tax_type, SUM(base_amount) as total_base, SUM(tax_amount) as total_tax, COUNT(*) as count')
            ->groupBy('tax_type')
            ->get()
            ->map(fn ($row) => [
                'tax_type' => $row->tax_type,
                'tax_type_label' => TaxType::from($row->tax_type)->label(),
                'total_base' => round((float) $row->total_base, 2),
                'total_tax' => round((float) $row->total_tax, 2),
                'count' => (int) $row->count,
            ]);

        return Inertia::render('Accounting/Tax/Index', [
            'transactions' => $transactions,
            'summary' => $summary,
            'filters' => [
                'period' => $period,
                'tax_type' => $taxType,
            ],
            'taxTypeOptions' => collect(TaxType::cases())->map(fn (TaxType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function markReported(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'string', 'size:7'],
            'tax_type' => ['nullable', 'string'],
        ]);

        $query = TaxTransaction::query()
            ->where('tax_period', $validated['period'])
            ->where('status', TaxTransactionStatus::Unreported->value);

        if (! empty($validated['tax_type'])) {
            $query->where('tax_type', $validated['tax_type']);
        }

        $count = $query->update(['status' => TaxTransactionStatus::Reported->value]);

        return back()->with('success', "{$count} tax transactions marked as reported.");
    }
}
