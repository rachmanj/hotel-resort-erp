<?php

namespace App\Actions\Accounting;

use App\Enums\BankStatementLineStatus;
use App\Enums\JournalEntryStatus;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationAudit;
use App\Models\BankReconciliationLine;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Accounting\BankReconciliation\BankBookLineFetcher;
use App\Services\Accounting\BankReconciliation\BankReconciliationMatchingService;
use App\Services\Accounting\GlPostingService;
use App\Services\Accounting\JournalEntryNumberService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PostBankAdjustmentAction
{
    public function __construct(
        private JournalEntryNumberService $journalEntryNumberService,
        private GlPostingService $glPostingService,
        private BankBookLineFetcher $bankBookLineFetcher,
        private BankReconciliationMatchingService $bankReconciliationMatchingService,
    ) {}

    public function __invoke(
        BankReconciliation $reconciliation,
        BankReconciliationLine $line,
        int $counterAccountId,
        string $description,
        User $actor,
    ): JournalEntry {
        return DB::transaction(function () use ($reconciliation, $line, $counterAccountId, $description, $actor): JournalEntry {
            $this->assertCanAdjust($reconciliation, $line, $counterAccountId);

            $reconciliation->loadMissing('bankAccount');
            $hotelId = (int) $reconciliation->bankAccount->hotel_id;
            $bankCoaId = (int) $reconciliation->bankAccount->chart_of_account_id;

            $amount = max((float) $line->debit, (float) $line->credit);
            $counterDebit = 0.0;
            $counterCredit = 0.0;
            $bankDebit = 0.0;
            $bankCredit = 0.0;

            if ((float) $line->debit > 0) {
                $counterDebit = $amount;
                $bankCredit = $amount;
            } else {
                $bankDebit = $amount;
                $counterCredit = $amount;
            }

            $journalDescription = sprintf(
                'Bank reconciliation adjustment (reconciliation #%d): %s',
                $reconciliation->id,
                $description,
            );

            $entry = JournalEntry::query()->create([
                'hotel_id' => $hotelId,
                'journal_no' => $this->journalEntryNumberService->nextNumber(),
                'entry_date' => $line->posting_date,
                'description' => $journalDescription,
                'status' => JournalEntryStatus::Posted->value,
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
                'posted_at' => now(),
            ]);

            JournalEntryLine::query()->create([
                'journal_entry_id' => $entry->id,
                'chart_of_account_id' => $counterAccountId,
                'description' => $description,
                'debit' => $counterDebit,
                'credit' => $counterCredit,
            ]);

            JournalEntryLine::query()->create([
                'journal_entry_id' => $entry->id,
                'chart_of_account_id' => $bankCoaId,
                'description' => $description,
                'debit' => $bankDebit,
                'credit' => $bankCredit,
            ]);

            $transactionDate = $line->posting_date->toDateString();

            $this->glPostingService->post([
                [
                    'hotel_id' => $hotelId,
                    'chart_of_account_id' => $counterAccountId,
                    'transaction_date' => $transactionDate,
                    'debit' => $counterDebit,
                    'credit' => $counterCredit,
                    'description' => $description,
                    'reference_number' => $entry->journal_no,
                    'source_type' => 'journal_entry',
                    'source_id' => $entry->id,
                ],
                [
                    'hotel_id' => $hotelId,
                    'chart_of_account_id' => $bankCoaId,
                    'transaction_date' => $transactionDate,
                    'debit' => $bankDebit,
                    'credit' => $bankCredit,
                    'description' => $description,
                    'reference_number' => $entry->journal_no,
                    'source_type' => 'journal_entry',
                    'source_id' => $entry->id,
                ],
            ]);

            $counterAccount = ChartOfAccount::query()->findOrFail($counterAccountId);
            $bankAccountCoa = ChartOfAccount::query()->findOrFail($bankCoaId);

            $matchStatusAfterAdjust = BankStatementLineStatus::Unmatched;
            foreach (BankStatementLineStatus::cases() as $case) {
                if ($case->value === 'adjusted') {
                    $matchStatusAfterAdjust = $case;
                    break;
                }
            }

            $line->update([
                'adjusting_journal_id' => $entry->id,
                'match_status' => $matchStatusAfterAdjust,
            ]);

            BankReconciliationAudit::query()->create([
                'bank_reconciliation_id' => $reconciliation->id,
                'action' => 'adjusted',
                'bank_line_ids' => [$line->id],
                'book_line_ids' => [],
                'amounts' => [
                    'amount' => $amount,
                    'journal_no' => $entry->journal_no,
                    'counter_account_code' => $counterAccount->account_code,
                    'bank_account_code' => $bankAccountCoa->account_code,
                ],
                'performed_by' => $actor->id,
                'notes' => $description,
            ]);

            $this->bankBookLineFetcher->fetchAndReplace($reconciliation);
            $this->bankBookLineFetcher->refreshStaleFlags($reconciliation);
            $this->bankReconciliationMatchingService->autoMatch($reconciliation);

            return $entry->fresh(['lines']);
        });
    }

    private function assertCanAdjust(
        BankReconciliation $reconciliation,
        BankReconciliationLine $line,
        int $counterAccountId,
    ): void {
        if ($reconciliation->isLockedForEditing()) {
            throw new InvalidArgumentException('This bank reconciliation is locked for editing.');
        }

        if ((int) $line->bank_reconciliation_id !== (int) $reconciliation->id) {
            throw new InvalidArgumentException('The statement line does not belong to this bank reconciliation.');
        }

        if ($line->match_status !== BankStatementLineStatus::Unmatched) {
            throw new InvalidArgumentException('Only unmatched statement lines can be adjusted.');
        }

        if ($line->adjusting_journal_id !== null) {
            throw new InvalidArgumentException('This statement line already has a bank adjustment journal.');
        }

        $reconciliation->loadMissing('bankAccount');

        if ($reconciliation->bankAccount->chart_of_account_id === null) {
            throw new InvalidArgumentException('The bank account is not linked to a chart of accounts entry.');
        }

        $amount = max((float) $line->debit, (float) $line->credit);
        if ($amount <= 0) {
            throw new InvalidArgumentException('The statement line amount must be greater than zero.');
        }

        $bankCoaId = (int) $reconciliation->bankAccount->chart_of_account_id;

        if ($counterAccountId === $bankCoaId) {
            throw new InvalidArgumentException('The counter account cannot be the same as the bank GL account.');
        }

        $counterAccount = ChartOfAccount::query()->find($counterAccountId);
        if ($counterAccount === null) {
            throw new InvalidArgumentException('The counter account does not exist.');
        }

        if (! $counterAccount->is_postable || ! $counterAccount->is_active) {
            throw new InvalidArgumentException('The counter account is not available for posting.');
        }
    }
}
