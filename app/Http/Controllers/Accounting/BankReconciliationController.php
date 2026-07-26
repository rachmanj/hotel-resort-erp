<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\GeneralLedger;
use App\Services\Accounting\BankReconciliationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankReconciliationController extends Controller
{
    public function __construct(
        private BankReconciliationService $bankReconciliationService,
    ) {}

    public function index(): Response
    {
        $reconciliations = BankReconciliation::query()
            ->with(['bankAccount', 'reconciledBy:id,name'])
            ->orderByDesc('period_end_date')
            ->paginate(20)
            ->through(fn (BankReconciliation $rec) => [
                'id' => $rec->id,
                'bank_name' => $rec->bankAccount?->bank_name,
                'account_no' => $rec->bankAccount?->account_no,
                'period_end_date' => $rec->period_end_date->toDateString(),
                'statement_balance' => (float) $rec->statement_balance,
                'book_balance' => (float) $rec->book_balance,
                'variance' => round((float) $rec->statement_balance - (float) $rec->book_balance, 2),
                'status' => $rec->status->value,
                'status_label' => $rec->status->label(),
                'reconciled_by' => $rec->reconciledBy?->name,
                'reconciled_at' => $rec->reconciled_at?->toDateTimeString(),
            ]);

        return Inertia::render('Accounting/BankReconciliation/Index', [
            'reconciliations' => $reconciliations,
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(['id', 'bank_name', 'account_no']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'period_end_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric'],
        ]);

        $bankAccount = BankAccount::query()->findOrFail($validated['bank_account_id']);

        $reconciliation = $this->bankReconciliationService->startReconciliation(
            $bankAccount,
            Carbon::parse($validated['period_end_date']),
            (float) $validated['statement_balance'],
        );

        return redirect()->route('accounting.bank-rec.reconcile', $reconciliation)->with('success', 'Reconciliation started.');
    }

    public function reconcile(BankReconciliation $bankReconciliation): Response
    {
        $bankReconciliation->load(['bankAccount.chartOfAccount', 'lines.generalLedger']);

        $periodEnd = Carbon::parse($bankReconciliation->period_end_date);
        $unmatchedLedger = $this->bankReconciliationService->getUnmatchedLedgerEntries(
            $bankReconciliation->bankAccount,
            $periodEnd,
        );

        return Inertia::render('Accounting/BankReconciliation/Reconcile', [
            'reconciliation' => [
                'id' => $bankReconciliation->id,
                'bank_name' => $bankReconciliation->bankAccount?->bank_name,
                'account_no' => $bankReconciliation->bankAccount?->account_no,
                'period_end_date' => $bankReconciliation->period_end_date->toDateString(),
                'statement_balance' => (float) $bankReconciliation->statement_balance,
                'book_balance' => (float) $bankReconciliation->book_balance,
                'status' => $bankReconciliation->status->value,
                'status_label' => $bankReconciliation->status->label(),
                'lines' => $bankReconciliation->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'statement_line_ref' => $line->statement_line_ref,
                    'statement_date' => $line->statement_date->toDateString(),
                    'statement_amount' => (float) $line->statement_amount,
                    'is_matched' => $line->is_matched,
                    'gl_description' => $line->generalLedger?->description,
                ]),
            ],
            'unmatchedLedger' => $unmatchedLedger->map(fn ($entry) => [
                'id' => $entry->id,
                'transaction_date' => $entry->transaction_date->toDateString(),
                'description' => $entry->description,
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
                'amount' => round((float) $entry->debit - (float) $entry->credit, 2),
            ]),
        ]);
    }

    public function importLines(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.statement_date' => ['required', 'date'],
            'lines.*.statement_amount' => ['required', 'numeric'],
            'lines.*.statement_line_ref' => ['nullable', 'string', 'max:100'],
        ]);

        $this->bankReconciliationService->importStatementLines($bankReconciliation, $validated['lines']);

        return back()->with('success', 'Statement lines imported.');
    }

    public function match(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        $validated = $request->validate([
            'line_id' => ['required', 'exists:bank_reconciliation_lines,id'],
            'general_ledger_id' => ['required', 'exists:general_ledger,id'],
        ]);

        $line = $bankReconciliation->lines()->findOrFail($validated['line_id']);
        $ledger = GeneralLedger::query()->findOrFail($validated['general_ledger_id']);

        $this->bankReconciliationService->matchLine($line, $ledger);

        return back()->with('success', 'Line matched.');
    }

    public function autoMatch(BankReconciliation $bankReconciliation): RedirectResponse
    {
        $matched = $this->bankReconciliationService->autoMatch($bankReconciliation);

        return back()->with('success', "{$matched} lines auto-matched.");
    }

    public function complete(Request $request, BankReconciliation $bankReconciliation): RedirectResponse
    {
        $this->bankReconciliationService->completeReconciliation($bankReconciliation, $request->user());

        return redirect()->route('accounting.bank-rec.index')->with('success', 'Reconciliation completed.');
    }
}
