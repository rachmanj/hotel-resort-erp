<?php

namespace App\Actions\Accounting;

use App\Enums\BankBookLineStatus;
use App\Enums\BankStatementLineStatus;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Support\BankReconciliationSupport;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CarryForwardOutstandingAction
{
    public function importOutstandingInto(BankReconciliation $reconciliation): int
    {
        $previous = $this->findPreviousSession($reconciliation);

        if ($previous === null) {
            return 0;
        }

        $periodLabel = $this->periodLabel($previous);
        $carryNote = "Carried forward from {$periodLabel}";

        $imported = 0;

        $existingStatementCarryIds = BankReconciliationLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->whereNotNull('carried_from_line_id')
            ->pluck('carried_from_line_id')
            ->all();

        $existingBookCarryIds = BankReconciliationBookLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->whereNotNull('carried_from_book_line_id')
            ->pluck('carried_from_book_line_id')
            ->all();

        $existingGlIds = BankReconciliationBookLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->whereNotNull('general_ledger_id')
            ->pluck('general_ledger_id')
            ->all();

        foreach ($previous->lines()->where('match_status', BankStatementLineStatus::Outstanding)->get() as $sourceLine) {
            if (in_array($sourceLine->id, $existingStatementCarryIds, true)) {
                continue;
            }

            $originId = $sourceLine->origin_reconciliation_id ?? $previous->id;
            $postingDate = $sourceLine->posting_date?->toDateString() ?? $sourceLine->statement_date?->toDateString();
            $direction = $sourceLine->direction ?? ((float) $sourceLine->debit > 0 ? 'debit' : 'credit');
            $amount = (float) $sourceLine->amount ?: max((float) $sourceLine->debit, (float) $sourceLine->credit);

            BankReconciliationLine::query()->create([
                'bank_reconciliation_id' => $reconciliation->id,
                'posting_date' => $postingDate,
                'value_date' => $sourceLine->value_date,
                'statement_date' => $sourceLine->statement_date,
                'statement_amount' => $sourceLine->statement_amount,
                'statement_line_ref' => $sourceLine->statement_line_ref,
                'description' => $sourceLine->description,
                'reference' => $sourceLine->reference,
                'debit' => $sourceLine->debit,
                'credit' => $sourceLine->credit,
                'amount' => $amount,
                'direction' => $direction,
                'running_balance' => $sourceLine->running_balance,
                'match_status' => BankStatementLineStatus::Unmatched,
                'line_notes' => $carryNote,
                'line_hash' => BankReconciliationSupport::lineHash(
                    (string) $postingDate,
                    $direction,
                    $amount,
                    $sourceLine->reference ?? $sourceLine->statement_line_ref,
                    ($sourceLine->description ?? '').'|cf:'.$sourceLine->id,
                ),
                'is_carried_forward' => true,
                'carried_from_line_id' => $sourceLine->id,
                'origin_reconciliation_id' => $originId,
            ]);

            $imported++;
        }

        foreach ($previous->bookLines()->where('match_status', BankBookLineStatus::Outstanding)->get() as $sourceBookLine) {
            if (in_array($sourceBookLine->id, $existingBookCarryIds, true)) {
                continue;
            }

            if ($sourceBookLine->general_ledger_id !== null
                && in_array($sourceBookLine->general_ledger_id, $existingGlIds, true)) {
                continue;
            }

            $originId = $sourceBookLine->origin_reconciliation_id ?? $previous->id;

            BankReconciliationBookLine::query()->create([
                'bank_reconciliation_id' => $reconciliation->id,
                'general_ledger_id' => $sourceBookLine->general_ledger_id,
                'posting_date' => $sourceBookLine->posting_date,
                'doc_num' => $sourceBookLine->doc_num,
                'reference_number' => $sourceBookLine->reference_number,
                'description' => $sourceBookLine->description,
                'debit' => $sourceBookLine->debit,
                'credit' => $sourceBookLine->credit,
                'match_status' => BankBookLineStatus::Unmatched,
                'line_notes' => $carryNote,
                'is_carried_forward' => true,
                'carried_from_book_line_id' => $sourceBookLine->id,
                'origin_reconciliation_id' => $originId,
            ]);

            $imported++;
        }

        return $imported;
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     description: string|null,
     *     amount: float,
     *     posting_date: string|null,
     *     days_outstanding: int,
     *     origin_period: string|null
     * }>
     */
    public function outstandingNeedingAttention(BankReconciliation $reconciliation, int $days = 60): Collection
    {
        $reconciliation->loadMissing('lines', 'bookLines');

        $fallbackPeriodEnd = $reconciliation->period_end_date ?? $reconciliation->periode;
        $today = Carbon::today();

        $statementItems = $reconciliation->lines()
            ->where('match_status', BankStatementLineStatus::Outstanding)
            ->get()
            ->map(fn (BankReconciliationLine $line): ?array => $this->mapAttentionItem(
                $line->id,
                $line->description,
                max((float) $line->debit, (float) $line->credit) ?: abs((float) $line->amount),
                $line->posting_date,
                $line->origin_reconciliation_id,
                $fallbackPeriodEnd,
                $today,
                $days,
            ))
            ->filter();

        $bookItems = $reconciliation->bookLines()
            ->where('match_status', BankBookLineStatus::Outstanding)
            ->get()
            ->map(fn (BankReconciliationBookLine $line): ?array => $this->mapAttentionItem(
                $line->id,
                $line->description,
                max((float) $line->debit, (float) $line->credit),
                $line->posting_date,
                $line->origin_reconciliation_id,
                $fallbackPeriodEnd,
                $today,
                $days,
            ))
            ->filter();

        return $statementItems->merge($bookItems)->values();
    }

    private function findPreviousSession(BankReconciliation $reconciliation): ?BankReconciliation
    {
        $currentEffective = $reconciliation->periode ?? $reconciliation->period_end_date;

        if ($currentEffective === null) {
            return null;
        }

        $currentEffective = Carbon::parse($currentEffective);

        return BankReconciliation::query()
            ->where('bank_account_id', $reconciliation->bank_account_id)
            ->where('id', '!=', $reconciliation->id)
            ->orderByDesc('periode')
            ->orderByDesc('period_end_date')
            ->get()
            ->first(function (BankReconciliation $candidate) use ($currentEffective): bool {
                $candidateEffective = $candidate->periode ?? $candidate->period_end_date;

                if ($candidateEffective === null) {
                    return false;
                }

                return Carbon::parse($candidateEffective)->lt($currentEffective);
            });
    }

    private function periodLabel(BankReconciliation $reconciliation): string
    {
        $date = $reconciliation->periode ?? $reconciliation->period_end_date;

        return $date !== null
            ? Carbon::parse($date)->format('M Y')
            : 'prior period';
    }

    /**
     * @return array{
     *     id: int,
     *     description: string|null,
     *     amount: float,
     *     posting_date: string|null,
     *     days_outstanding: int,
     *     origin_period: string|null
     * }|null
     */
    private function mapAttentionItem(
        int $id,
        ?string $description,
        float $amount,
        ?CarbonInterface $postingDate,
        ?int $originReconciliationId,
        mixed $sessionFallbackPeriodEnd,
        CarbonInterface $today,
        int $days,
    ): ?array {
        $originPeriodEnd = $this->resolveOriginPeriodEnd($originReconciliationId, $sessionFallbackPeriodEnd);

        if ($originPeriodEnd === null) {
            return null;
        }

        $originEnd = Carbon::parse($originPeriodEnd)->startOfDay();
        $daysOutstanding = (int) $originEnd->diffInDays($today, false);

        if ($daysOutstanding <= $days) {
            return null;
        }

        return [
            'id' => $id,
            'description' => $description,
            'amount' => round($amount, 2),
            'posting_date' => $postingDate?->toDateString(),
            'days_outstanding' => $daysOutstanding,
            'origin_period' => $originEnd->format('M Y'),
        ];
    }

    private function resolveOriginPeriodEnd(?int $originReconciliationId, mixed $sessionFallbackPeriodEnd): ?string
    {
        if ($originReconciliationId !== null) {
            $origin = BankReconciliation::query()->find($originReconciliationId);

            if ($origin !== null) {
                $end = $origin->period_end_date ?? $origin->periode;

                return $end !== null ? Carbon::parse($end)->toDateString() : null;
            }
        }

        if ($sessionFallbackPeriodEnd === null) {
            return null;
        }

        return Carbon::parse($sessionFallbackPeriodEnd)->toDateString();
    }
}
