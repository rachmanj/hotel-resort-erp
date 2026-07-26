<?php

namespace App\Services\Accounting;

use App\Enums\BankReconciliationStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\GeneralLedger;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankReconciliationService
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function startReconciliation(
        BankAccount $bankAccount,
        Carbon $periodEndDate,
        float $statementBalance,
    ): BankReconciliation {
        $bookBalance = $this->getBookBalance($bankAccount, $periodEndDate);

        return BankReconciliation::query()->create([
            'bank_account_id' => $bankAccount->id,
            'period_end_date' => $periodEndDate->toDateString(),
            'statement_balance' => $statementBalance,
            'book_balance' => $bookBalance,
            'status' => BankReconciliationStatus::InProgress->value,
        ]);
    }

    /**
     * @param  array<int, array{statement_line_ref?: string|null, statement_date: string, statement_amount: float}>  $statementLines
     */
    public function importStatementLines(BankReconciliation $reconciliation, array $statementLines): Collection
    {
        return collect($statementLines)->map(function (array $line) use ($reconciliation): BankReconciliationLine {
            return BankReconciliationLine::query()->create([
                'bank_reconciliation_id' => $reconciliation->id,
                'statement_line_ref' => $line['statement_line_ref'] ?? null,
                'statement_date' => $line['statement_date'],
                'statement_amount' => $line['statement_amount'],
            ]);
        });
    }

    public function matchLine(BankReconciliationLine $line, GeneralLedger $ledgerEntry): BankReconciliationLine
    {
        if ($line->is_matched) {
            throw new InvalidArgumentException('Statement line is already matched.');
        }

        $line->update([
            'general_ledger_id' => $ledgerEntry->id,
            'is_matched' => true,
            'matched_at' => now(),
        ]);

        return $line->fresh();
    }

    public function completeReconciliation(BankReconciliation $reconciliation, User $user): BankReconciliation
    {
        if ($reconciliation->status === BankReconciliationStatus::Completed) {
            throw new InvalidArgumentException('Reconciliation is already completed.');
        }

        $unmatched = $reconciliation->lines()->where('is_matched', false)->count();
        if ($unmatched > 0) {
            throw new InvalidArgumentException("{$unmatched} statement lines remain unmatched.");
        }

        $reconciliation->update([
            'status' => BankReconciliationStatus::Completed->value,
            'reconciled_by' => $user->id,
            'reconciled_at' => now(),
        ]);

        return $reconciliation->fresh();
    }

    public function getBookBalance(BankAccount $bankAccount, Carbon $asOfDate): float
    {
        return $this->glPostingService->getBalance(
            $bankAccount->chartOfAccount,
            $asOfDate,
            (int) $bankAccount->hotel_id,
        );
    }

    /**
     * @return Collection<int, GeneralLedger>
     */
    public function getUnmatchedLedgerEntries(BankAccount $bankAccount, Carbon $periodEndDate): Collection
    {
        $matchedIds = BankReconciliationLine::query()
            ->whereHas('bankReconciliation', fn ($q) => $q->where('bank_account_id', $bankAccount->id))
            ->whereNotNull('general_ledger_id')
            ->pluck('general_ledger_id');

        return GeneralLedger::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $bankAccount->hotel_id)
            ->where('chart_of_account_id', $bankAccount->chart_of_account_id)
            ->where('transaction_date', '<=', $periodEndDate->toDateString())
            ->when($matchedIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $matchedIds))
            ->orderBy('transaction_date')
            ->get();
    }

    public function autoMatch(BankReconciliation $reconciliation): int
    {
        $bankAccount = $reconciliation->bankAccount;
        $periodEnd = Carbon::parse($reconciliation->period_end_date);
        $ledgerEntries = $this->getUnmatchedLedgerEntries($bankAccount, $periodEnd);
        $matched = 0;

        DB::transaction(function () use ($reconciliation, $ledgerEntries, &$matched): void {
            foreach ($reconciliation->lines()->where('is_matched', false)->get() as $line) {
                $statementAmount = round((float) $line->statement_amount, 2);

                $candidate = $ledgerEntries->first(function (GeneralLedger $entry) use ($line, $statementAmount): bool {
                    $entryAmount = round((float) $entry->debit - (float) $entry->credit, 2);

                    return $entry->transaction_date->toDateString() === $line->statement_date->toDateString()
                        && abs($entryAmount - $statementAmount) < 0.01;
                });

                if ($candidate !== null) {
                    $this->matchLine($line, $candidate);
                    $ledgerEntries = $ledgerEntries->reject(fn (GeneralLedger $e) => $e->id === $candidate->id);
                    $matched++;
                }
            }
        });

        return $matched;
    }
}
