<?php

namespace App\Actions\Accounting;

use App\Enums\JournalEntryStatus;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationAudit;
use App\Models\BankReconciliationLine;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Accounting\GlPostingService;
use App\Services\Accounting\JournalEntryNumberService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReverseBankAdjustmentAction
{
    public function __construct(
        private JournalEntryNumberService $journalEntryNumberService,
        private GlPostingService $glPostingService,
    ) {}

    public function __invoke(
        BankReconciliation $reconciliation,
        BankReconciliationLine $line,
        User $actor,
    ): JournalEntry {
        return DB::transaction(function () use ($reconciliation, $line, $actor): JournalEntry {
            if ($reconciliation->isLockedForEditing()) {
                throw new InvalidArgumentException('This bank reconciliation is locked for editing.');
            }

            if ($line->adjusting_journal_id === null) {
                throw new InvalidArgumentException('This statement line has no bank adjustment to reverse.');
            }

            $original = JournalEntry::query()
                ->with('lines')
                ->findOrFail($line->adjusting_journal_id);

            $reconciliation->loadMissing('bankAccount');
            $hotelId = (int) $reconciliation->bankAccount->hotel_id;

            $reversalDescription = sprintf(
                'Reversal of bank reconciliation adjustment %s (reconciliation #%d)',
                $original->journal_no,
                $reconciliation->id,
            );

            $reversal = JournalEntry::query()->create([
                'hotel_id' => $hotelId,
                'journal_no' => $this->journalEntryNumberService->nextNumber(),
                'entry_date' => $original->entry_date,
                'description' => $reversalDescription,
                'status' => JournalEntryStatus::Posted->value,
                'created_by' => $actor->id,
                'approved_by' => $actor->id,
                'posted_at' => now(),
                'reversed_from_id' => $original->id,
            ]);

            $glLines = [];

            foreach ($original->lines as $originalLine) {
                JournalEntryLine::query()->create([
                    'journal_entry_id' => $reversal->id,
                    'chart_of_account_id' => $originalLine->chart_of_account_id,
                    'department_id' => $originalLine->department_id,
                    'description' => $originalLine->description,
                    'debit' => (float) $originalLine->credit,
                    'credit' => (float) $originalLine->debit,
                ]);

                $glLines[] = [
                    'hotel_id' => $hotelId,
                    'chart_of_account_id' => $originalLine->chart_of_account_id,
                    'department_id' => $originalLine->department_id,
                    'transaction_date' => $reversal->entry_date->toDateString(),
                    'debit' => (float) $originalLine->credit,
                    'credit' => (float) $originalLine->debit,
                    'description' => $originalLine->description ?? $reversalDescription,
                    'reference_number' => $reversal->journal_no,
                    'source_type' => 'journal_entry',
                    'source_id' => $reversal->id,
                ];
            }

            $this->glPostingService->post($glLines);

            $line->update(['adjusting_journal_id' => null]);

            BankReconciliationAudit::query()->create([
                'bank_reconciliation_id' => $reconciliation->id,
                'action' => 'adjustment_reversed',
                'bank_line_ids' => [$line->id],
                'book_line_ids' => [],
                'amounts' => [
                    'original_journal_no' => $original->journal_no,
                    'reversal_journal_no' => $reversal->journal_no,
                ],
                'performed_by' => $actor->id,
                'notes' => $reversalDescription,
            ]);

            return $reversal->fresh(['lines']);
        });
    }
}
