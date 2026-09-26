<?php

namespace App\Http\Controllers\Accounting;

use App\Actions\Accounting\CarryForwardOutstandingAction;
use App\Actions\Accounting\PostBankAdjustmentAction;
use App\Actions\Accounting\ReverseBankAdjustmentAction;
use App\Enums\BankBookLineStatus;
use App\Enums\BankReconciliationStatus;
use App\Enums\BankStatementLineStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExcludeReconciliationLineRequest;
use App\Http\Requests\ImportBankStatementLinesRequest;
use App\Http\Requests\ManualMatchBankReconciliationRequest;
use App\Http\Requests\PostBankAdjustmentRequest;
use App\Http\Requests\RejectBankReconciliationRequest;
use App\Http\Requests\ReopenBankReconciliationRequest;
use App\Http\Requests\StoreBankReconciliationRequest;
use App\Http\Requests\UpdateBankReconciliationBalancesRequest;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\BankReconciliationMatch;
use App\Models\GeneralLedger;
use App\Models\User;
use App\Services\Accounting\BankReconciliation\BankBookLineFetcher;
use App\Services\Accounting\BankReconciliation\BankReconciliationMatchingService;
use App\Services\Accounting\BankReconciliation\BankReconciliationWorkflowService;
use App\Services\Accounting\BankReconciliationService;
use App\Support\BankReconciliationSupport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class BankReconciliationController extends Controller
{
    public function __construct(
        private BankReconciliationMatchingService $matchingService,
        private BankReconciliationWorkflowService $workflowService,
        private BankBookLineFetcher $bookLineFetcher,
        private BankReconciliationService $legacyBankReconciliationService,
        private CarryForwardOutstandingAction $carryForwardOutstandingAction,
        private PostBankAdjustmentAction $postBankAdjustmentAction,
        private ReverseBankAdjustmentAction $reverseBankAdjustmentAction,
    ) {}

    public function index(): Response
    {
        $hotelId = (int) session('current_hotel_id');

        $reconciliations = BankReconciliation::query()
            ->with(['bankAccount', 'reconciledBy:id,name', 'validatedBy:id,name'])
            ->whereHas('bankAccount', fn ($query) => $query->where('hotel_id', $hotelId))
            ->orderByDesc('period_end_date')
            ->paginate(20)
            ->through(fn (BankReconciliation $rec) => [
                'id' => $rec->id,
                'bank_name' => $rec->bankAccount?->bank_name,
                'account_no' => $rec->bankAccount?->account_no,
                'period_end_date' => $rec->period_end_date->toDateString(),
                'statement_balance' => (float) ($rec->statement_closing_balance ?? $rec->statement_balance),
                'book_balance' => (float) ($rec->book_closing_balance ?? $rec->book_balance),
                'variance' => round(
                    (float) ($rec->statement_closing_balance ?? $rec->statement_balance)
                    - (float) ($rec->book_closing_balance ?? $rec->book_balance),
                    2,
                ),
                'status' => $rec->status->value,
                'status_label' => $rec->status->label(),
                'reconciled_by' => $rec->validatedBy?->name ?? $rec->reconciledBy?->name,
                'reconciled_at' => $rec->validated_at?->toDateTimeString() ?? $rec->reconciled_at?->toDateTimeString(),
            ]);

        return Inertia::render('Accounting/BankReconciliation/Index', [
            'reconciliations' => $reconciliations,
            'bankAccounts' => BankAccount::query()->where('is_active', true)->orderBy('bank_name')->get(['id', 'bank_name', 'account_no']),
        ]);
    }

    public function store(StoreBankReconciliationRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $bankAccount = BankAccount::query()->findOrFail($validated['bank_account_id']);
        $periodEnd = Carbon::parse($validated['period_end_date']);
        $periode = $periodEnd->copy()->startOfMonth();
        $closingBalance = (float) $validated['statement_balance'];

        try {
            $bookBalance = $this->legacyBankReconciliationService->getBookBalance($bankAccount, $periodEnd);

            $reconciliation = DB::transaction(function () use ($request, $bankAccount, $periodEnd, $periode, $closingBalance, $bookBalance): BankReconciliation {
                $reconciliation = BankReconciliation::query()->create([
                    'bank_account_id' => $bankAccount->id,
                    'periode' => $periode->toDateString(),
                    'period_end_date' => $periodEnd->toDateString(),
                    'statement_opening_balance' => null,
                    'statement_closing_balance' => $closingBalance,
                    'book_opening_balance' => null,
                    'book_closing_balance' => $bookBalance,
                    'statement_balance' => $closingBalance,
                    'book_balance' => $bookBalance,
                    'statement_format' => 'manual',
                    'source_mode' => 'manual',
                    'status' => BankReconciliationStatus::InReview,
                    'created_by' => $request->user()->id,
                ]);

                $this->carryForwardOutstandingAction->importOutstandingInto($reconciliation);
                $this->bookLineFetcher->fetchAndReplace($reconciliation);

                return $reconciliation->fresh();
            });
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('accounting.bank-rec.reconcile', $reconciliation)
            ->with('success', 'Reconciliation started.');
    }

    public function reconcile(BankReconciliation $bankReconciliation): Response
    {
        $bankReconciliation->load(['bankAccount.chartOfAccount', 'lines.generalLedger']);

        $periodEnd = Carbon::parse($bankReconciliation->period_end_date);
        $unmatchedLedger = $this->legacyBankReconciliationService->getUnmatchedLedgerEntries(
            $bankReconciliation->bankAccount,
            $periodEnd,
        );

        return Inertia::render('Accounting/BankReconciliation/Reconcile', [
            'reconciliation' => [
                'id' => $bankReconciliation->id,
                'bank_name' => $bankReconciliation->bankAccount?->bank_name,
                'account_no' => $bankReconciliation->bankAccount?->account_no,
                'period_end_date' => $bankReconciliation->period_end_date->toDateString(),
                'statement_balance' => (float) ($bankReconciliation->statement_closing_balance ?? $bankReconciliation->statement_balance),
                'book_balance' => (float) ($bankReconciliation->book_closing_balance ?? $bankReconciliation->book_balance),
                'status' => $bankReconciliation->status->value,
                'status_label' => $bankReconciliation->status->label(),
                'lines' => $bankReconciliation->lines->map(fn (BankReconciliationLine $line) => [
                    'id' => $line->id,
                    'statement_line_ref' => $line->statement_line_ref,
                    'statement_date' => ($line->statement_date ?? $line->posting_date)?->toDateString(),
                    'statement_amount' => (float) ($line->statement_amount ?: max((float) $line->debit, (float) $line->credit)),
                    'is_matched' => $this->lineIsMatched($line),
                    'gl_description' => $line->generalLedger?->description,
                ]),
            ],
            'unmatchedLedger' => $unmatchedLedger->map(fn (GeneralLedger $entry) => [
                'id' => $entry->id,
                'transaction_date' => $entry->transaction_date->toDateString(),
                'description' => $entry->description,
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
                'amount' => round((float) $entry->debit - (float) $entry->credit, 2),
            ]),
        ]);
    }

    public function importLines(
        ImportBankStatementLinesRequest $request,
        BankReconciliation $bankReconciliation,
    ): RedirectResponse {
        try {
            foreach ($request->validated('lines') as $line) {
                $this->createManualStatementLine($bankReconciliation, $line);
            }
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Statement lines imported.');
    }

    public function updateBalances(
        UpdateBankReconciliationBalancesRequest $request,
        BankReconciliation $bankReconciliation,
    ): RedirectResponse {
        if ($bankReconciliation->isLockedForEditing()) {
            return back()->with('error', 'This bank reconciliation is locked for editing.');
        }

        $validated = $request->validated();

        $statementClosing = $validated['statement_closing_balance'] ?? $validated['statement_balance'] ?? null;
        $bookClosing = $validated['book_closing_balance'] ?? $validated['book_balance'] ?? null;

        $bankReconciliation->update([
            'statement_opening_balance' => $validated['statement_opening_balance'] ?? $bankReconciliation->statement_opening_balance,
            'statement_closing_balance' => $statementClosing ?? $bankReconciliation->statement_closing_balance,
            'book_opening_balance' => $validated['book_opening_balance'] ?? $bankReconciliation->book_opening_balance,
            'book_closing_balance' => $bookClosing ?? $bankReconciliation->book_closing_balance,
            'statement_balance' => $statementClosing ?? $bankReconciliation->statement_balance,
            'book_balance' => $bookClosing ?? $bankReconciliation->book_balance,
        ]);

        return back()->with('success', 'Balances updated.');
    }

    public function refreshBookLines(BankReconciliation $bankReconciliation): RedirectResponse
    {
        try {
            $count = $this->bookLineFetcher->fetchAndReplace($bankReconciliation);
            $staleCount = $this->bookLineFetcher->refreshStaleFlags($bankReconciliation);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $message = "{$count} book line(s) loaded.";
        if ($staleCount > 0) {
            $message .= " {$staleCount} stale line(s) flagged.";
        }

        return back()->with('success', $message);
    }

    public function autoMatch(BankReconciliation $bankReconciliation): RedirectResponse
    {
        try {
            $matched = $this->matchingService->autoMatch($bankReconciliation);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "{$matched} match group(s) created.");
    }

    public function match(
        ManualMatchBankReconciliationRequest $request,
        BankReconciliation $bankReconciliation,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            if (isset($validated['line_id'])) {
                $this->matchLegacyGlLine(
                    $bankReconciliation,
                    (int) $validated['line_id'],
                    (int) $validated['general_ledger_id'],
                    $request->user(),
                );
            } else {
                $this->matchingService->manualMatch(
                    $bankReconciliation,
                    $validated['statement_line_ids'],
                    $validated['book_line_ids'],
                    $request->user(),
                );
            }
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Lines matched.');
    }

    public function unmatch(
        BankReconciliation $bankReconciliation,
        BankReconciliationMatch $match,
        Request $request,
    ): RedirectResponse {
        try {
            $this->matchingService->unmatchGroup($bankReconciliation, $match, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Match removed.');
    }

    public function excludeStatementLine(
        ExcludeReconciliationLineRequest $request,
        BankReconciliation $bankReconciliation,
        BankReconciliationLine $line,
    ): RedirectResponse {
        try {
            $this->matchingService->excludeStatementLine(
                $bankReconciliation,
                $line,
                $request->validated('reason'),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Statement line excluded.');
    }

    public function includeStatementLine(
        BankReconciliation $bankReconciliation,
        BankReconciliationLine $line,
        Request $request,
    ): RedirectResponse {
        try {
            $this->matchingService->includeStatementLine($bankReconciliation, $line, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Statement line included.');
    }

    public function markStatementLineOutstanding(
        ExcludeReconciliationLineRequest $request,
        BankReconciliation $bankReconciliation,
        BankReconciliationLine $line,
    ): RedirectResponse {
        try {
            $this->matchingService->markStatementLineOutstanding(
                $bankReconciliation,
                $line,
                $request->validated('reason'),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Statement line marked outstanding.');
    }

    public function excludeBookLine(
        ExcludeReconciliationLineRequest $request,
        BankReconciliation $bankReconciliation,
        BankReconciliationBookLine $bookLine,
    ): RedirectResponse {
        try {
            $this->matchingService->excludeBookLine(
                $bankReconciliation,
                $bookLine,
                $request->validated('reason'),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Book line excluded.');
    }

    public function includeBookLine(
        BankReconciliation $bankReconciliation,
        BankReconciliationBookLine $bookLine,
        Request $request,
    ): RedirectResponse {
        try {
            $this->matchingService->includeBookLine($bankReconciliation, $bookLine, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Book line included.');
    }

    public function markBookLineOutstanding(
        ExcludeReconciliationLineRequest $request,
        BankReconciliation $bankReconciliation,
        BankReconciliationBookLine $bookLine,
    ): RedirectResponse {
        try {
            $this->matchingService->markBookLineOutstanding(
                $bankReconciliation,
                $bookLine,
                $request->validated('reason'),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Book line marked outstanding.');
    }

    public function postAdjustment(
        PostBankAdjustmentRequest $request,
        BankReconciliation $bankReconciliation,
    ): RedirectResponse {
        $validated = $request->validated();
        $line = $bankReconciliation->lines()->findOrFail($validated['statement_line_id']);

        try {
            $this->postBankAdjustmentAction->__invoke(
                $bankReconciliation,
                $line,
                (int) $validated['counter_account_id'],
                $validated['description'],
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Bank adjustment posted.');
    }

    public function reverseAdjustment(
        BankReconciliation $bankReconciliation,
        BankReconciliationLine $line,
        Request $request,
    ): RedirectResponse {
        try {
            $this->reverseBankAdjustmentAction->__invoke($bankReconciliation, $line, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Bank adjustment reversed.');
    }

    public function submitForValidation(BankReconciliation $bankReconciliation, Request $request): RedirectResponse
    {
        try {
            $this->workflowService->submitForValidation($bankReconciliation, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Reconciliation submitted for validation.');
    }

    public function validateReconciliation(BankReconciliation $bankReconciliation, Request $request): RedirectResponse
    {
        try {
            $this->workflowService->validateReconciliation($bankReconciliation, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('accounting.bank-rec.index')
            ->with('success', 'Reconciliation validated and completed.');
    }

    public function rejectReconciliation(
        RejectBankReconciliationRequest $request,
        BankReconciliation $bankReconciliation,
    ): RedirectResponse {
        try {
            $this->workflowService->rejectReconciliation(
                $bankReconciliation,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Reconciliation rejected.');
    }

    public function reopenReconciliation(
        ReopenBankReconciliationRequest $request,
        BankReconciliation $bankReconciliation,
    ): RedirectResponse {
        try {
            $this->workflowService->reopenReconciliation(
                $bankReconciliation,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Reconciliation reopened.');
    }

    public function voidReconciliation(
        RejectBankReconciliationRequest $request,
        BankReconciliation $bankReconciliation,
    ): RedirectResponse {
        try {
            $this->workflowService->voidReconciliation(
                $bankReconciliation,
                $request->user(),
                $request->validated('reason'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('accounting.bank-rec.index')
            ->with('success', 'Reconciliation voided.');
    }

    public function status(BankReconciliation $bankReconciliation): JsonResponse
    {
        return response()->json($this->workflowService->statusPayloadFor($bankReconciliation));
    }

    private function lineIsMatched(BankReconciliationLine $line): bool
    {
        if ($line->is_matched) {
            return true;
        }

        return $line->match_status !== null
            && $line->match_status !== BankStatementLineStatus::Unmatched;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function createManualStatementLine(BankReconciliation $reconciliation, array $line): BankReconciliationLine
    {
        if ($reconciliation->isLockedForEditing()) {
            throw new InvalidArgumentException('This bank reconciliation is locked for editing.');
        }

        $postingDate = Carbon::parse($line['statement_date'])->toDateString();
        $signedAmount = (float) $line['statement_amount'];
        $amount = round(abs($signedAmount), 2);
        $direction = $signedAmount < 0 ? 'debit' : 'credit';
        $debit = $direction === 'debit' ? $amount : 0.0;
        $credit = $direction === 'credit' ? $amount : 0.0;
        $reference = $line['statement_line_ref'] ?? null;
        $description = $line['description'] ?? null;

        return BankReconciliationLine::query()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $postingDate,
            'statement_date' => $postingDate,
            'statement_amount' => $signedAmount,
            'statement_line_ref' => $reference,
            'description' => $description,
            'reference' => $reference,
            'debit' => $debit,
            'credit' => $credit,
            'amount' => $amount,
            'direction' => $direction,
            'match_status' => BankStatementLineStatus::Unmatched,
            'line_hash' => BankReconciliationSupport::lineHash(
                $postingDate,
                $direction,
                $amount,
                $reference,
                $description,
            ),
        ]);
    }

    private function matchLegacyGlLine(
        BankReconciliation $reconciliation,
        int $statementLineId,
        int $generalLedgerId,
        ?User $user,
    ): void {
        $statementLine = $reconciliation->lines()->findOrFail($statementLineId);

        $this->bookLineFetcher->fetchAndReplace($reconciliation);

        $bookLine = $reconciliation->bookLines()
            ->where('general_ledger_id', $generalLedgerId)
            ->first();

        if ($bookLine === null) {
            $ledger = GeneralLedger::query()
                ->withoutGlobalScope('hotel')
                ->where('id', $generalLedgerId)
                ->firstOrFail();

            $bookLine = BankReconciliationBookLine::query()->create([
                'bank_reconciliation_id' => $reconciliation->id,
                'general_ledger_id' => $ledger->id,
                'posting_date' => $ledger->transaction_date->toDateString(),
                'doc_num' => $ledger->reference_number,
                'reference_number' => $ledger->reference_number,
                'description' => $ledger->description,
                'debit' => $ledger->debit,
                'credit' => $ledger->credit,
                'match_status' => BankBookLineStatus::Unmatched,
            ]);
        }

        $this->matchingService->manualMatch(
            $reconciliation,
            [$statementLine->id],
            [$bookLine->id],
            $user,
        );
    }
}
