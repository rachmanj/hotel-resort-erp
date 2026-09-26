<?php

namespace App\Http\Controllers\Accounting;

use App\Actions\Accounting\CarryForwardOutstandingAction;
use App\Actions\Accounting\PostBankAdjustmentAction;
use App\Actions\Accounting\ReverseBankAdjustmentAction;
use App\Enums\BankBookLineStatus;
use App\Enums\BankReconciliationStatus;
use App\Enums\BankReconciliationValidationStatus;
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
use App\Models\ChartOfAccount;
use App\Models\GeneralLedger;
use App\Models\User;
use App\Services\Accounting\BankReconciliation\BankBookLineFetcher;
use App\Services\Accounting\BankReconciliation\BankReconciliationMatchingService;
use App\Services\Accounting\BankReconciliation\BankReconciliationWorkflowService;
use App\Services\Accounting\BankReconciliationService;
use App\Support\BankReconciliationSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
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

    public function index(Request $request): Response
    {
        $hotelId = (int) session('current_hotel_id');

        $query = BankReconciliation::query()
            ->with(['bankAccount', 'validatedBy:id,name', 'createdBy:id,name'])
            ->withCount([
                'lines',
                'lines as matched_statement_lines_count' => fn ($q) => $q->where(
                    'match_status',
                    '!=',
                    BankStatementLineStatus::Unmatched->value,
                ),
            ])
            ->whereHas('bankAccount', fn ($builder) => $builder->where('hotel_id', $hotelId));

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('validation_status')) {
            $query->where('validation_status', $request->string('validation_status'));
        }

        if ($request->boolean('pending_validation')) {
            $query->where('validation_status', BankReconciliationValidationStatus::Pending->value);
        }

        if ($request->filled('bank_account_id')) {
            $query->where('bank_account_id', $request->integer('bank_account_id'));
        }

        if ($request->filled('period_from')) {
            $query->whereDate('period_end_date', '>=', $request->string('period_from'));
        }

        if ($request->filled('period_to')) {
            $query->whereDate('period_end_date', '<=', $request->string('period_to'));
        }

        $reconciliations = $query
            ->orderByDesc('period_end_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BankReconciliation $rec) => [
                'id' => $rec->id,
                'bank_account_id' => $rec->bank_account_id,
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
                'statement_lines_count' => $rec->lines_count,
                'matched_statement_lines_count' => (int) $rec->matched_statement_lines_count,
                'status' => $rec->status->value,
                'status_label' => $rec->status->label(),
                'validation_status' => $rec->validation_status?->value,
                'validation_status_label' => $rec->validation_status?->label(),
                'validator_name' => $rec->validatedBy?->name,
                'updated_at' => $rec->updated_at?->toDateTimeString(),
            ]);

        return Inertia::render('Accounting/BankReconciliation/Index', [
            'reconciliations' => $reconciliations,
            'bankAccounts' => BankAccount::query()
                ->where('hotel_id', $hotelId)
                ->where('is_active', true)
                ->orderBy('bank_name')
                ->get(['id', 'bank_name', 'account_no']),
            'filters' => [
                'status' => $request->string('status')->toString() ?: null,
                'validation_status' => $request->string('validation_status')->toString() ?: null,
                'bank_account_id' => $request->filled('bank_account_id') ? $request->integer('bank_account_id') : null,
                'period_from' => $request->string('period_from')->toString() ?: null,
                'period_to' => $request->string('period_to')->toString() ?: null,
                'pending_validation' => $request->boolean('pending_validation'),
            ],
            'statusOptions' => collect(BankReconciliationStatus::cases())->map(fn (BankReconciliationStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values()->all(),
            'validationStatusOptions' => collect(BankReconciliationValidationStatus::cases())->map(
                fn (BankReconciliationValidationStatus $status) => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
            )->values()->all(),
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

    public function reconcile(Request $request, BankReconciliation $bankReconciliation): Response
    {
        $hotelId = (int) session('current_hotel_id');
        $user = $request->user();

        $bankReconciliation->load([
            'bankAccount.chartOfAccount',
            'lines.adjustingJournal',
            'lines.matchLine',
            'bookLines.matchLedger',
            'matches.createdBy:id,name',
            'matches.matchLines',
            'matches.matchLedgerRows',
            'createdBy:id,name',
            'submittedBy:id,name',
            'validatedBy:id,name',
        ]);

        $postableAccounts = ChartOfAccount::query()
            ->where('hotel_id', $hotelId)
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'name']);

        $accountsByCode = $postableAccounts->keyBy('account_code');

        $statusPayload = $this->workflowService->statusPayloadFor($bankReconciliation);

        return Inertia::render('Accounting/BankReconciliation/Reconcile', [
            'reconciliation' => $this->mapReconciliationHeader($bankReconciliation),
            'statementLines' => $bankReconciliation->lines
                ->sortBy('posting_date')
                ->values()
                ->map(fn (BankReconciliationLine $line) => $this->mapStatementLine($line, $accountsByCode))
                ->all(),
            'bookLines' => $bankReconciliation->bookLines
                ->sortBy('posting_date')
                ->values()
                ->map(fn (BankReconciliationBookLine $line) => $this->mapBookLine($line))
                ->all(),
            'matchGroups' => $bankReconciliation->matches
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (BankReconciliationMatch $match) => $this->mapMatchGroup($match))
                ->all(),
            'statusPayload' => $statusPayload,
            'submitChecklist' => $this->buildSubmitChecklist($statusPayload),
            'postableAccounts' => $postableAccounts->map(fn (ChartOfAccount $account) => [
                'id' => $account->id,
                'account_code' => $account->account_code,
                'name' => $account->name,
                'label' => "{$account->account_code} — {$account->name}",
            ])->values()->all(),
            'isPreparer' => $user !== null && $bankReconciliation->isPreparer($user->id),
            'isEditable' => $bankReconciliation->status->isEditable() && ! $bankReconciliation->isLockedForEditing(),
        ]);
    }

    public function report(BankReconciliation $bankReconciliation): Response
    {
        return Inertia::render('Accounting/BankReconciliation/Report', $this->buildReportPageProps($bankReconciliation));
    }

    public function reportDownload(BankReconciliation $bankReconciliation): HttpResponse
    {
        $data = $this->buildReportDocumentData($bankReconciliation);

        $pdf = Pdf::loadView('bank-reconciliation.report', $data);

        $accountNo = $data['header']['account_no'] ?? 'bank';
        $period = $data['header']['period_end_date'] ?? 'report';

        return $pdf->download("bank-reconciliation-{$accountNo}-{$period}.pdf");
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

    /**
     * @return array<string, mixed>
     */
    private function mapReconciliationHeader(BankReconciliation $bankReconciliation): array
    {
        return [
            'id' => $bankReconciliation->id,
            'bank_name' => $bankReconciliation->bankAccount?->bank_name,
            'account_no' => $bankReconciliation->bankAccount?->account_no,
            'period_end_date' => $bankReconciliation->period_end_date->toDateString(),
            'periode' => $bankReconciliation->periode?->toDateString(),
            'statement_opening_balance' => $bankReconciliation->statement_opening_balance !== null
                ? (float) $bankReconciliation->statement_opening_balance
                : null,
            'statement_closing_balance' => (float) ($bankReconciliation->statement_closing_balance ?? $bankReconciliation->statement_balance),
            'book_closing_balance' => (float) ($bankReconciliation->book_closing_balance ?? $bankReconciliation->book_balance),
            'status' => $bankReconciliation->status->value,
            'status_label' => $bankReconciliation->status->label(),
            'validation_status' => $bankReconciliation->validation_status?->value,
            'validation_status_label' => $bankReconciliation->validation_status?->label(),
            'rejection_reason' => $bankReconciliation->rejection_reason,
            'prepared_by' => $bankReconciliation->createdBy?->name,
            'prepared_at' => $bankReconciliation->created_at?->toDateTimeString(),
            'submitted_by' => $bankReconciliation->submittedBy?->name,
            'submitted_at' => $bankReconciliation->submitted_at?->toDateTimeString(),
            'validated_by' => $bankReconciliation->validatedBy?->name,
            'validated_at' => $bankReconciliation->validated_at?->toDateTimeString(),
        ];
    }

    /**
     * @param  Collection<string, ChartOfAccount>  $accountsByCode
     * @return array<string, mixed>
     */
    private function mapStatementLine(BankReconciliationLine $line, $accountsByCode): array
    {
        $description = $line->description ?? $line->generalLedger?->description ?? '';
        $suggestedCode = BankReconciliationSupport::suggestCounterAccountCode($description);
        $suggestedAccount = $suggestedCode !== null ? $accountsByCode->get($suggestedCode) : null;

        return [
            'id' => $line->id,
            'posting_date' => ($line->statement_date ?? $line->posting_date)?->toDateString(),
            'description' => $description,
            'reference' => $line->reference ?? $line->statement_line_ref,
            'statement_line_ref' => $line->statement_line_ref,
            'debit' => (float) $line->debit,
            'credit' => (float) $line->credit,
            'net_amount' => $line->netAmount(),
            'running_balance' => $line->running_balance !== null ? (float) $line->running_balance : null,
            'match_status' => $line->match_status?->value,
            'match_status_label' => $line->match_status?->label(),
            'match_group_id' => $line->matchLine?->bank_reconciliation_match_id,
            'exclude_reason' => $line->exclude_reason,
            'is_ai_extracted' => (bool) $line->is_ai_extracted,
            'is_carried_forward' => (bool) $line->is_carried_forward,
            'adjusting_journal_id' => $line->adjusting_journal_id,
            'adjusting_journal_no' => $line->adjustingJournal?->journal_no,
            'suggested_counter_account_code' => $suggestedCode,
            'suggested_counter_account_id' => $suggestedAccount?->id,
            'suggested_counter_account_name' => $suggestedAccount?->name,
            'can_adjust' => $line->match_status === BankStatementLineStatus::Unmatched
                && $line->adjusting_journal_id === null,
            'is_manual_line' => ! $line->is_ai_extracted && ! $line->is_carried_forward,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBookLine(BankReconciliationBookLine $line): array
    {
        return [
            'id' => $line->id,
            'posting_date' => $line->posting_date?->toDateString(),
            'doc_num' => $line->doc_num,
            'reference' => $line->reference_number ?? $line->doc_num,
            'description' => $line->description,
            'debit' => (float) $line->debit,
            'credit' => (float) $line->credit,
            'net_amount' => $line->netAmount(),
            'match_status' => $line->match_status?->value,
            'match_status_label' => $line->match_status?->label(),
            'match_group_id' => $line->matchLedger?->bank_reconciliation_match_id,
            'exclude_reason' => $line->exclude_reason,
            'is_stale' => (bool) $line->is_stale,
            'stale_reason' => $line->stale_reason,
            'is_carried_forward' => (bool) $line->is_carried_forward,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapMatchGroup(BankReconciliationMatch $match): array
    {
        return [
            'id' => $match->id,
            'match_type' => $match->match_type?->value,
            'match_type_label' => $match->match_type?->label(),
            'bank_total' => (float) $match->bank_total,
            'book_total' => (float) $match->book_total,
            'difference' => (float) $match->difference,
            'matched_by' => $match->createdBy?->name,
            'matched_at' => $match->created_at?->toDateTimeString(),
            'statement_line_ids' => $match->matchLines->pluck('bank_reconciliation_line_id')->values()->all(),
            'book_line_ids' => $match->matchLedgerRows->pluck('bank_reconciliation_book_line_id')->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $statusPayload
     * @return array<int, array<string, mixed>>
     */
    private function buildSubmitChecklist(array $statusPayload): array
    {
        $tolerance = BankReconciliationSupport::TOLERANCE;
        $unmatchedBank = (int) ($statusPayload['unmatched_bank_count'] ?? 0);
        $unmatchedBook = (int) ($statusPayload['unmatched_book_count'] ?? 0);
        $difference = abs((float) ($statusPayload['difference'] ?? 0));
        $unexplained = abs((float) ($statusPayload['unexplained_difference'] ?? 0));
        $crossFoot = $statusPayload['cross_foot'] ?? null;
        $staleCount = (int) ($statusPayload['stale_lines_count'] ?? 0);
        $incomplete = (bool) ($statusPayload['incomplete'] ?? true);

        return [
            [
                'key' => 'balances',
                'label' => 'Statement and book closing balances are recorded',
                'passed' => ! $incomplete,
            ],
            [
                'key' => 'unmatched',
                'label' => 'All statement and book lines are matched, excluded, outstanding, or adjusted',
                'passed' => $unmatchedBank === 0 && $unmatchedBook === 0,
                'detail' => $unmatchedBank > 0 || $unmatchedBook > 0
                    ? "{$unmatchedBank} statement / {$unmatchedBook} book line(s) still unmatched"
                    : null,
            ],
            [
                'key' => 'cleared_difference',
                'label' => 'Cleared lines net to zero',
                'passed' => $difference < $tolerance,
                'detail' => $difference >= $tolerance
                    ? 'Cleared difference: '.number_format((float) $statusPayload['difference'], 2, '.', ',')
                    : null,
            ],
            [
                'key' => 'cross_foot',
                'label' => 'Statement movement cross-foots with opening and closing balances',
                'passed' => $crossFoot === true,
                'detail' => $crossFoot === false ? 'Cross-foot check failed' : ($crossFoot === null ? 'Opening balance required for cross-foot' : null),
            ],
            [
                'key' => 'unexplained_difference',
                'label' => 'Unexplained difference is zero',
                'passed' => $unexplained < $tolerance,
                'detail' => $unexplained >= $tolerance
                    ? 'Unexplained difference: '.number_format((float) $statusPayload['unexplained_difference'], 2, '.', ',')
                    : null,
            ],
            [
                'key' => 'stale_lines',
                'label' => 'No stale book lines',
                'passed' => $staleCount === 0,
                'detail' => $staleCount > 0 ? "{$staleCount} stale book line(s) — refresh book lines and review" : null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportPageProps(BankReconciliation $bankReconciliation): array
    {
        $document = $this->buildReportDocumentData($bankReconciliation);

        return [
            'header' => $document['header'],
            'balanceProof' => $document['balance_proof'],
            'statementItems' => $document['statement_items'],
            'bookItems' => $document['book_items'],
            'adjustments' => $document['adjustments'],
            'signOff' => $document['sign_off'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportDocumentData(BankReconciliation $bankReconciliation): array
    {
        $bankReconciliation->load([
            'bankAccount',
            'lines.adjustingJournal',
            'bookLines',
            'createdBy:id,name',
            'submittedBy:id,name',
            'validatedBy:id,name',
        ]);

        $statusPayload = $this->workflowService->statusPayloadFor($bankReconciliation);

        $statementItems = $bankReconciliation->lines
            ->filter(fn (BankReconciliationLine $line): bool => in_array($line->match_status, [
                BankStatementLineStatus::Unmatched,
                BankStatementLineStatus::Excluded,
                BankStatementLineStatus::Outstanding,
            ], true))
            ->map(fn (BankReconciliationLine $line) => [
                'date' => ($line->statement_date ?? $line->posting_date)?->toDateString(),
                'description' => $line->description,
                'reference' => $line->reference ?? $line->statement_line_ref,
                'status' => $line->match_status?->label(),
                'amount' => $line->netAmount(),
            ])
            ->values()
            ->all();

        $bookItems = $bankReconciliation->bookLines
            ->filter(fn (BankReconciliationBookLine $line): bool => in_array($line->match_status, [
                BankBookLineStatus::Unmatched,
                BankBookLineStatus::Excluded,
                BankBookLineStatus::Outstanding,
            ], true))
            ->map(fn (BankReconciliationBookLine $line) => [
                'date' => $line->posting_date?->toDateString(),
                'description' => $line->description,
                'reference' => $line->reference_number ?? $line->doc_num,
                'status' => $line->match_status?->label(),
                'amount' => $line->netAmount(),
            ])
            ->values()
            ->all();

        $adjustments = $bankReconciliation->lines
            ->filter(fn (BankReconciliationLine $line): bool => $line->adjusting_journal_id !== null)
            ->map(fn (BankReconciliationLine $line) => [
                'date' => ($line->statement_date ?? $line->posting_date)?->toDateString(),
                'description' => $line->description,
                'journal_no' => $line->adjustingJournal?->journal_no,
                'amount' => $line->netAmount(),
            ])
            ->values()
            ->all();

        return [
            'company' => [
                'name' => config('invoice.company_name'),
                'title' => 'Bank Reconciliation Report',
            ],
            'header' => [
                'id' => $bankReconciliation->id,
                'bank_name' => $bankReconciliation->bankAccount?->bank_name,
                'account_no' => $bankReconciliation->bankAccount?->account_no,
                'period_end_date' => $bankReconciliation->period_end_date->toDateString(),
                'periode' => $bankReconciliation->periode?->toDateString(),
                'status' => $bankReconciliation->status->label(),
                'validation_status' => $bankReconciliation->validation_status?->label(),
                'validator_name' => $bankReconciliation->validatedBy?->name,
                'generated_at' => now()->toDateTimeString(),
            ],
            'balance_proof' => [
                'statement_closing' => (float) ($statusPayload['statement_closing'] ?? 0),
                'deposits_in_transit' => (float) ($statusPayload['deposits_in_transit'] ?? 0),
                'outstanding_checks' => (float) ($statusPayload['outstanding_checks'] ?? 0),
                'adjusted_statement_balance' => (float) ($statusPayload['adjusted_statement_balance'] ?? 0),
                'book_closing' => (float) ($statusPayload['book_closing'] ?? 0),
                'unexplained_difference' => (float) ($statusPayload['unexplained_difference'] ?? 0),
                'cleared_difference' => (float) ($statusPayload['difference'] ?? 0),
            ],
            'statement_items' => $statementItems,
            'book_items' => $bookItems,
            'adjustments' => $adjustments,
            'sign_off' => [
                'prepared_by' => $bankReconciliation->createdBy?->name,
                'prepared_at' => $bankReconciliation->created_at?->toDateTimeString(),
                'submitted_by' => $bankReconciliation->submittedBy?->name,
                'submitted_at' => $bankReconciliation->submitted_at?->toDateTimeString(),
                'validated_by' => $bankReconciliation->validatedBy?->name,
                'validated_at' => $bankReconciliation->validated_at?->toDateTimeString(),
            ],
        ];
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
