<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\BudgetDepartment;
use App\Enums\BudgetStatus;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BudgetController extends Controller
{
    public function index(Request $request): Response
    {
        $year = (int) ($request->integer('year') ?: now()->year);

        $budgets = Budget::query()
            ->with('createdBy:id,name')
            ->where('fiscal_year', $year)
            ->orderBy('department')
            ->get()
            ->map(fn (Budget $budget) => [
                'id' => $budget->id,
                'department' => $budget->department->value,
                'department_label' => $budget->department->label(),
                'fiscal_year' => $budget->fiscal_year,
                'status' => $budget->status->value,
                'status_label' => $budget->status->label(),
                'created_by' => $budget->createdBy?->name,
                'total_budgeted' => (float) $budget->lines()->sum('budgeted_amount'),
            ]);

        return Inertia::render('Accounting/Budget/Index', [
            'budgets' => $budgets,
            'year' => $year,
            'departments' => collect(BudgetDepartment::cases())->map(fn (BudgetDepartment $d) => [
                'value' => $d->value,
                'label' => $d->label(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department' => ['required', 'string'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $exists = Budget::query()
            ->where('department', $validated['department'])
            ->where('fiscal_year', $validated['fiscal_year'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'Budget already exists for this department and year.');
        }

        $budget = Budget::query()->create([
            'department' => $validated['department'],
            'fiscal_year' => $validated['fiscal_year'],
            'status' => BudgetStatus::Draft->value,
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('accounting.budgets.edit', $budget)->with('success', 'Budget created.');
    }

    public function edit(Budget $budget): Response
    {
        $budget->load('lines.chartOfAccount');

        $accounts = ChartOfAccount::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->whereIn('account_type', ['expense', 'revenue', 'cogs'])
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'name']);

        return Inertia::render('Accounting/Budget/Edit', [
            'budget' => [
                'id' => $budget->id,
                'department' => $budget->department->value,
                'department_label' => $budget->department->label(),
                'fiscal_year' => $budget->fiscal_year,
                'status' => $budget->status->value,
                'status_label' => $budget->status->label(),
                'lines' => $budget->lines->map(fn (BudgetLine $line) => [
                    'id' => $line->id,
                    'chart_of_account_id' => $line->chart_of_account_id,
                    'account_code' => $line->chartOfAccount?->account_code,
                    'account_name' => $line->chartOfAccount?->name,
                    'month' => $line->month,
                    'budgeted_amount' => (float) $line->budgeted_amount,
                ]),
            ],
            'accounts' => $accounts,
        ]);
    }

    public function updateLines(Request $request, Budget $budget): RedirectResponse
    {
        if ($budget->status === BudgetStatus::Approved) {
            return back()->with('error', 'Approved budgets cannot be edited.');
        }

        $validated = $request->validate([
            'lines' => ['required', 'array'],
            'lines.*.chart_of_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'lines.*.month' => ['required', 'integer', 'min:1', 'max:12'],
            'lines.*.budgeted_amount' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($budget, $validated): void {
            $budget->lines()->delete();

            foreach ($validated['lines'] as $line) {
                BudgetLine::query()->create([
                    'budget_id' => $budget->id,
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'month' => $line['month'],
                    'budgeted_amount' => $line['budgeted_amount'],
                ]);
            }
        });

        return back()->with('success', 'Budget lines saved.');
    }

    public function approve(Budget $budget): RedirectResponse
    {
        $budget->update(['status' => BudgetStatus::Approved->value]);

        return back()->with('success', 'Budget approved.');
    }

    public function actual(Request $request): Response
    {
        $year = (int) ($request->integer('year') ?: now()->year);
        $month = $request->filled('month') ? (int) $request->integer('month') : null;

        $budgets = Budget::query()
            ->with(['lines.chartOfAccount'])
            ->where('fiscal_year', $year)
            ->where('status', BudgetStatus::Approved->value)
            ->get();

        $rows = collect();

        foreach ($budgets as $budget) {
            foreach ($budget->lines as $line) {
                if ($month !== null && $line->month !== $month) {
                    continue;
                }

                $startDate = sprintf('%04d-%02d-01', $year, $line->month);
                $endDate = date('Y-m-t', strtotime($startDate));

                $actual = GeneralLedger::query()
                    ->where('chart_of_account_id', $line->chart_of_account_id)
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->selectRaw('COALESCE(SUM(debit), 0) - COALESCE(SUM(credit), 0) as net')
                    ->value('net');

                $budgeted = (float) $line->budgeted_amount;
                $actualAmount = round(abs((float) $actual), 2);
                $variance = round($budgeted - $actualAmount, 2);

                $rows->push([
                    'department' => $budget->department->label(),
                    'account_code' => $line->chartOfAccount?->account_code,
                    'account_name' => $line->chartOfAccount?->name,
                    'month' => $line->month,
                    'budgeted' => $budgeted,
                    'actual' => $actualAmount,
                    'variance' => $variance,
                    'variance_pct' => $budgeted > 0 ? round(($variance / $budgeted) * 100, 1) : 0,
                ]);
            }
        }

        return Inertia::render('Accounting/Budget/Actual', [
            'rows' => $rows->values(),
            'year' => $year,
            'month' => $month,
        ]);
    }
}
