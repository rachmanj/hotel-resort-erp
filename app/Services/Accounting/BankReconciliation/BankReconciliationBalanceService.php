<?php

namespace App\Services\Accounting\BankReconciliation;

use App\Enums\BankBookLineStatus;
use App\Enums\BankStatementLineStatus;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Support\BankReconciliationSupport;
use Illuminate\Support\Collection;

class BankReconciliationBalanceService
{
    public function bankNet(BankReconciliation $r): float
    {
        return $this->sumClearedNet($this->statementLines($r));
    }

    public function bookNet(BankReconciliation $r): float
    {
        return $this->sumClearedNet($this->bookLines($r));
    }

    public function difference(BankReconciliation $r): float
    {
        return round($this->bankNet($r) + $this->bookNet($r), 2);
    }

    public function unmatchedBankCount(BankReconciliation $r): int
    {
        return $this->statementLines($r)
            ->filter(fn (BankReconciliationLine $line): bool => $line->match_status === BankStatementLineStatus::Unmatched)
            ->count();
    }

    public function unmatchedBookCount(BankReconciliation $r): int
    {
        return $this->bookLines($r)
            ->filter(fn (BankReconciliationBookLine $line): bool => $line->match_status === BankBookLineStatus::Unmatched)
            ->count();
    }

    public function depositsInTransit(BankReconciliation $r): float
    {
        return round(
            $this->bookLines($r)
                ->filter(fn (BankReconciliationBookLine $line): bool => $line->match_status === BankBookLineStatus::Outstanding)
                ->filter(fn (BankReconciliationBookLine $line): bool => $line->netAmount() > 0)
                ->sum(fn (BankReconciliationBookLine $line): float => $line->netAmount()),
            2,
        );
    }

    public function outstandingChecks(BankReconciliation $r): float
    {
        return round(
            abs(
                $this->bookLines($r)
                    ->filter(fn (BankReconciliationBookLine $line): bool => $line->match_status === BankBookLineStatus::Outstanding)
                    ->filter(fn (BankReconciliationBookLine $line): bool => $line->netAmount() < 0)
                    ->sum(fn (BankReconciliationBookLine $line): float => $line->netAmount()),
            ),
            2,
        );
    }

    public function outstandingBankNet(BankReconciliation $r): float
    {
        return round(
            $this->statementLines($r)
                ->filter(fn (BankReconciliationLine $line): bool => $line->match_status === BankStatementLineStatus::Outstanding)
                ->sum(fn (BankReconciliationLine $line): float => $line->netAmount()),
            2,
        );
    }

    public function statementOpening(BankReconciliation $r): ?float
    {
        return $this->nullableMoney($r->statement_opening_balance);
    }

    public function statementClosing(BankReconciliation $r): ?float
    {
        return $this->nullableMoney($r->statement_closing_balance);
    }

    public function bookClosing(BankReconciliation $r): ?float
    {
        return $this->nullableMoney($r->book_closing_balance);
    }

    public function hasStatementBalances(BankReconciliation $r): bool
    {
        return $this->statementOpening($r) !== null
            && $this->statementClosing($r) !== null;
    }

    public function adjustedStatementBalance(BankReconciliation $r): float
    {
        $closing = $this->statementClosing($r) ?? 0.0;

        return round(
            $closing
            + $this->depositsInTransit($r)
            - $this->outstandingChecks($r)
            - $this->outstandingBankNet($r),
            2,
        );
    }

    public function reconciliationDifference(BankReconciliation $r): float
    {
        $bookClosing = $this->bookClosing($r) ?? 0.0;

        return round($this->adjustedStatementBalance($r) - $bookClosing, 2);
    }

    public function crossFoot(BankReconciliation $r): ?bool
    {
        if (! $this->hasStatementBalances($r)) {
            return null;
        }

        $opening = $this->statementOpening($r) ?? 0.0;
        $closing = $this->statementClosing($r) ?? 0.0;
        $expected = round($closing - $opening, 2);

        $movementSum = round(
            $this->statementLines($r)
                ->reject(fn (BankReconciliationLine $line): bool => $line->match_status === BankStatementLineStatus::Excluded)
                ->sum(fn (BankReconciliationLine $line): float => $line->netAmount()),
            2,
        );

        $actual = round(-1 * $movementSum, 2);

        return abs($actual - $expected) < BankReconciliationSupport::TOLERANCE;
    }

    public function unexplainedDifference(BankReconciliation $r): float
    {
        $statementClosing = $this->statementClosing($r) ?? 0.0;
        $bookClosing = $this->bookClosing($r) ?? 0.0;

        $adjustedBank = $statementClosing + $this->unmatchedBookNet($r);
        $adjustedBook = $bookClosing - $this->unmatchedBankNet($r);

        return round($adjustedBank - $adjustedBook, 2);
    }

    public function isBalanced(BankReconciliation $r): bool
    {
        if ($this->unmatchedBankCount($r) > 0 || $this->unmatchedBookCount($r) > 0) {
            return false;
        }

        if (abs($this->difference($r)) >= BankReconciliationSupport::TOLERANCE) {
            return false;
        }

        $crossFoot = $this->crossFoot($r);
        if ($crossFoot === false) {
            return false;
        }

        return abs($this->unexplainedDifference($r)) < BankReconciliationSupport::TOLERANCE;
    }

    public function incomplete(BankReconciliation $r): bool
    {
        return ! $this->hasStatementBalances($r) || $this->bookClosing($r) === null;
    }

    public function diagnostic(BankReconciliation $r): string
    {
        if (! $this->hasStatementBalances($r) || $this->bookClosing($r) === null) {
            return 'Closing balances are required on both the statement and the book side before this reconciliation can be submitted.';
        }

        if ($this->isBalanced($r)) {
            return 'Balanced.';
        }

        return sprintf(
            'Unmatched lines: %d statement / %d book. Cleared difference: %s. Unexplained difference: %s.',
            $this->unmatchedBankCount($r),
            $this->unmatchedBookCount($r),
            $this->formatMoney($this->difference($r)),
            $this->formatMoney($this->unexplainedDifference($r)),
        );
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function statusPayload(BankReconciliation $r): array
    {
        return [
            'bank_net' => $this->bankNet($r),
            'book_net' => $this->bookNet($r),
            'difference' => $this->difference($r),
            'unmatched_bank_count' => $this->unmatchedBankCount($r),
            'unmatched_book_count' => $this->unmatchedBookCount($r),
            'deposits_in_transit' => $this->depositsInTransit($r),
            'outstanding_checks' => $this->outstandingChecks($r),
            'outstanding_bank_net' => $this->outstandingBankNet($r),
            'statement_opening' => $this->statementOpening($r),
            'statement_closing' => $this->statementClosing($r),
            'book_closing' => $this->bookClosing($r),
            'adjusted_statement_balance' => $this->adjustedStatementBalance($r),
            'reconciliation_difference' => $this->reconciliationDifference($r),
            'unexplained_difference' => $this->unexplainedDifference($r),
            'cross_foot' => $this->crossFoot($r),
            'is_balanced' => $this->isBalanced($r),
            'incomplete' => $this->incomplete($r),
            'diagnostic' => $this->diagnostic($r),
        ];
    }

    /**
     * @param  Collection<int, BankReconciliationLine|BankReconciliationBookLine>  $lines
     */
    private function sumClearedNet(Collection $lines): float
    {
        return round(
            $lines
                ->reject(fn (BankReconciliationLine|BankReconciliationBookLine $line): bool => $this->isExcludedFromClearedNet($line))
                ->sum(fn (BankReconciliationLine|BankReconciliationBookLine $line): float => $line->netAmount()),
            2,
        );
    }

    private function isExcludedFromClearedNet(BankReconciliationLine|BankReconciliationBookLine $line): bool
    {
        if ($line instanceof BankReconciliationLine) {
            return in_array($line->match_status, [
                BankStatementLineStatus::Excluded,
                BankStatementLineStatus::Outstanding,
            ], true);
        }

        return in_array($line->match_status, [
            BankBookLineStatus::Excluded,
            BankBookLineStatus::Outstanding,
        ], true);
    }

    private function unmatchedBankNet(BankReconciliation $r): float
    {
        return round(
            $this->statementLines($r)
                ->filter(fn (BankReconciliationLine $line): bool => $line->match_status === BankStatementLineStatus::Unmatched)
                ->sum(fn (BankReconciliationLine $line): float => $line->netAmount()),
            2,
        );
    }

    private function unmatchedBookNet(BankReconciliation $r): float
    {
        return round(
            $this->bookLines($r)
                ->filter(fn (BankReconciliationBookLine $line): bool => $line->match_status === BankBookLineStatus::Unmatched)
                ->sum(fn (BankReconciliationBookLine $line): float => $line->netAmount()),
            2,
        );
    }

    /**
     * @return Collection<int, BankReconciliationLine>
     */
    private function statementLines(BankReconciliation $r): Collection
    {
        if (! $r->relationLoaded('lines')) {
            $r->load('lines');
        }

        return $r->lines;
    }

    /**
     * @return Collection<int, BankReconciliationBookLine>
     */
    private function bookLines(BankReconciliation $r): Collection
    {
        if (! $r->relationLoaded('bookLines')) {
            $r->load('bookLines');
        }

        return $r->bookLines;
    }

    private function nullableMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }
}
