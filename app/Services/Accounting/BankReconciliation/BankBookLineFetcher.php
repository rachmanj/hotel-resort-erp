<?php

namespace App\Services\Accounting\BankReconciliation;

use App\Enums\BankBookLineStatus;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\GeneralLedger;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankBookLineFetcher
{
    public function fetchAndReplace(BankReconciliation $reconciliation): int
    {
        if ($reconciliation->isLockedForEditing()) {
            throw new InvalidArgumentException('This bank reconciliation is locked for editing.');
        }

        return (int) DB::transaction(function () use ($reconciliation): int {
            $reconciliation->loadMissing('bankAccount');

            $hotelId = (int) $reconciliation->bankAccount->hotel_id;
            $chartOfAccountId = (int) $reconciliation->bankAccount->chart_of_account_id;

            $periodStart = $this->periodStart($reconciliation);
            $periodEnd = $this->periodEnd($reconciliation);

            $ledgerRows = $this->ledgerRowsInPeriod(
                $hotelId,
                $chartOfAccountId,
                $periodStart,
                $periodEnd,
            );

            $fetchedGlIds = $ledgerRows->pluck('id')->all();

            BankReconciliationBookLine::query()
                ->where('bank_reconciliation_id', $reconciliation->id)
                ->where('match_status', BankBookLineStatus::Unmatched)
                ->where('is_carried_forward', false)
                ->whereNotNull('general_ledger_id')
                ->when(
                    $fetchedGlIds !== [],
                    fn ($query) => $query->whereNotIn('general_ledger_id', $fetchedGlIds),
                    fn ($query) => $query,
                )
                ->delete();

            $existingGlIds = BankReconciliationBookLine::query()
                ->where('bank_reconciliation_id', $reconciliation->id)
                ->whereNotNull('general_ledger_id')
                ->pluck('general_ledger_id')
                ->all();

            $existingGlIdLookup = array_fill_keys($existingGlIds, true);

            $now = now();
            $insertRows = [];

            foreach ($ledgerRows as $ledgerRow) {
                if (isset($existingGlIdLookup[$ledgerRow->id])) {
                    continue;
                }

                $insertRows[] = [
                    'bank_reconciliation_id' => $reconciliation->id,
                    'general_ledger_id' => $ledgerRow->id,
                    'posting_date' => $ledgerRow->transaction_date->toDateString(),
                    'doc_num' => $ledgerRow->reference_number,
                    'reference_number' => $ledgerRow->reference_number,
                    'description' => $ledgerRow->description,
                    'debit' => $ledgerRow->debit,
                    'credit' => $ledgerRow->credit,
                    'match_status' => BankBookLineStatus::Unmatched->value,
                    'is_stale' => false,
                    'stale_reason' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($insertRows !== []) {
                BankReconciliationBookLine::query()->insert($insertRows);
            }

            $opening = $this->openingBalance($reconciliation);
            $closing = round($opening + $this->periodMovement($reconciliation), 2);

            $reconciliation->update([
                'book_opening_balance' => $opening,
                'book_closing_balance' => $closing,
            ]);

            return BankReconciliationBookLine::query()
                ->where('bank_reconciliation_id', $reconciliation->id)
                ->count();
        });
    }

    public function refreshStaleFlags(BankReconciliation $reconciliation): int
    {
        $bookLines = BankReconciliationBookLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('is_carried_forward', false)
            ->whereNotNull('general_ledger_id')
            ->get();

        if ($bookLines->isEmpty()) {
            return 0;
        }

        $ledgerRows = GeneralLedger::query()
            ->withoutGlobalScope('hotel')
            ->whereIn('id', $bookLines->pluck('general_ledger_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $staleCount = 0;

        foreach ($bookLines as $bookLine) {
            $ledgerRow = $ledgerRows->get($bookLine->general_ledger_id);

            if ($ledgerRow === null) {
                $this->markStale($bookLine, 'The general ledger row no longer exists.');
                $staleCount++;

                continue;
            }

            $staleReason = $this->staleReasonForLedgerDrift($bookLine, $ledgerRow);

            if ($staleReason !== null) {
                $this->markStale($bookLine, $staleReason);
                $staleCount++;

                continue;
            }

            if ($bookLine->is_stale || $bookLine->stale_reason !== null) {
                $bookLine->update([
                    'is_stale' => false,
                    'stale_reason' => null,
                ]);
            }
        }

        return $staleCount;
    }

    /**
     * @return Collection<int, array{id: int, posting_date: string|null, description: string|null, debit: string, credit: string, stale_reason: string|null}>
     */
    public function staleLines(BankReconciliation $reconciliation): Collection
    {
        return BankReconciliationBookLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('is_stale', true)
            ->orderBy('posting_date')
            ->orderBy('id')
            ->get(['id', 'posting_date', 'description', 'debit', 'credit', 'stale_reason'])
            ->map(fn (BankReconciliationBookLine $line): array => [
                'id' => $line->id,
                'posting_date' => $line->posting_date?->toDateString(),
                'description' => $line->description,
                'debit' => (string) $line->debit,
                'credit' => (string) $line->credit,
                'stale_reason' => $line->stale_reason,
            ]);
    }

    public function openingBalance(BankReconciliation $reconciliation): float
    {
        $reconciliation->loadMissing('bankAccount');

        $hotelId = (int) $reconciliation->bankAccount->hotel_id;
        $chartOfAccountId = (int) $reconciliation->bankAccount->chart_of_account_id;
        $periodStart = $this->periodStart($reconciliation);

        return $this->sumNetBeforeDate($hotelId, $chartOfAccountId, $periodStart);
    }

    public function periodMovement(BankReconciliation $reconciliation): float
    {
        $reconciliation->loadMissing('bankAccount');

        $hotelId = (int) $reconciliation->bankAccount->hotel_id;
        $chartOfAccountId = (int) $reconciliation->bankAccount->chart_of_account_id;
        $periodStart = $this->periodStart($reconciliation);
        $periodEnd = $this->periodEnd($reconciliation);

        return $this->sumNetBetweenDates($hotelId, $chartOfAccountId, $periodStart, $periodEnd);
    }

    private function periodStart(BankReconciliation $reconciliation): Carbon
    {
        if ($reconciliation->periode !== null) {
            return Carbon::parse($reconciliation->periode)->startOfDay();
        }

        return Carbon::parse($reconciliation->period_end_date)->startOfMonth()->startOfDay();
    }

    private function periodEnd(BankReconciliation $reconciliation): Carbon
    {
        return Carbon::parse($reconciliation->period_end_date)->endOfDay();
    }

    /**
     * @return Collection<int, GeneralLedger>
     */
    private function ledgerRowsInPeriod(
        int $hotelId,
        int $chartOfAccountId,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): Collection {
        return GeneralLedger::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotelId)
            ->where('chart_of_account_id', $chartOfAccountId)
            ->whereDate('transaction_date', '>=', $periodStart->toDateString())
            ->whereDate('transaction_date', '<=', $periodEnd->toDateString())
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
    }

    private function sumNetBeforeDate(int $hotelId, int $chartOfAccountId, Carbon $beforeDate): float
    {
        $totals = GeneralLedger::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotelId)
            ->where('chart_of_account_id', $chartOfAccountId)
            ->whereDate('transaction_date', '<', $beforeDate->toDateString())
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = (float) ($totals->total_debit ?? 0);
        $credit = (float) ($totals->total_credit ?? 0);

        return round($debit - $credit, 2);
    }

    private function sumNetBetweenDates(
        int $hotelId,
        int $chartOfAccountId,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): float {
        $totals = GeneralLedger::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotelId)
            ->where('chart_of_account_id', $chartOfAccountId)
            ->whereDate('transaction_date', '>=', $periodStart->toDateString())
            ->whereDate('transaction_date', '<=', $periodEnd->toDateString())
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = (float) ($totals->total_debit ?? 0);
        $credit = (float) ($totals->total_credit ?? 0);

        return round($debit - $credit, 2);
    }

    private function staleReasonForLedgerDrift(
        BankReconciliationBookLine $bookLine,
        GeneralLedger $ledgerRow,
    ): ?string {
        $reasons = [];

        if (round((float) $bookLine->debit, 2) !== round((float) $ledgerRow->debit, 2)) {
            $reasons[] = sprintf(
                'Ledger debit changed from %s to %s.',
                $this->formatMoney((float) $bookLine->debit),
                $this->formatMoney((float) $ledgerRow->debit),
            );
        }

        if (round((float) $bookLine->credit, 2) !== round((float) $ledgerRow->credit, 2)) {
            $reasons[] = sprintf(
                'Ledger credit changed from %s to %s.',
                $this->formatMoney((float) $bookLine->credit),
                $this->formatMoney((float) $ledgerRow->credit),
            );
        }

        $bookDate = $bookLine->posting_date?->toDateString();
        $ledgerDate = $ledgerRow->transaction_date->toDateString();

        if ($bookDate !== $ledgerDate) {
            $reasons[] = sprintf(
                'Ledger transaction date changed from %s to %s.',
                $bookDate ?? 'null',
                $ledgerDate,
            );
        }

        if ($reasons === []) {
            return null;
        }

        return implode(' ', $reasons);
    }

    private function markStale(BankReconciliationBookLine $bookLine, string $reason): void
    {
        if ($bookLine->is_stale && $bookLine->stale_reason === $reason) {
            return;
        }

        $bookLine->update([
            'is_stale' => true,
            'stale_reason' => $reason,
        ]);
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
}
