<?php

namespace App\Services\Accounting\BankReconciliation;

use App\Enums\BankBookLineStatus;
use App\Enums\BankMatchType;
use App\Enums\BankStatementLineStatus;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationAudit;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\BankReconciliationMatch;
use App\Models\BankReconciliationMatchLedger;
use App\Models\BankReconciliationMatchLine;
use App\Models\User;
use App\Support\BankReconciliationSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankReconciliationMatchingService
{
    private const REFERENCE_DATE_WINDOW_DAYS = 7;

    private const EXACT_DATE_WINDOW_DAYS = 1;

    private const FUZZY_DATE_WINDOW_DAYS = 5;

    private const FUZZY_MIN_SCORE = 40;

    private const SPLIT_DATE_WINDOW_DAYS = 7;

    private const SPLIT_MAX_NEARBY_CANDIDATES = 20;

    private const SPLIT_MIN_LINES = 2;

    private const SPLIT_MAX_LINES = 5;

    public function __construct(
        private BankReconciliationBalanceService $balanceService,
    ) {}

    public function clearAutoMatches(BankReconciliation $r): int
    {
        return DB::transaction(function () use ($r): int {
            $this->assertEditable($r);

            return $this->clearAutoMatchesInternal($r);
        });
    }

    public function autoMatch(BankReconciliation $r): int
    {
        return DB::transaction(function () use ($r): int {
            $this->assertEditable($r);
            $this->refreshLineRelations($r);

            $this->clearAutoMatchesInternal($r);

            $created = 0;
            $created += $this->matchByReference($r);
            $created += $this->matchExact($r);
            $created += $this->matchFuzzy($r);
            $created += $this->matchSplitManyBookToOneBank($r);
            $created += $this->matchSplitManyBankToOneBook($r);

            return $created;
        });
    }

    private function clearAutoMatchesInternal(BankReconciliation $r): int
    {
        $removed = 0;

        $this->refreshMatchRelations($r);

        foreach ($r->matches as $match) {
            if (! $match->match_type->isAutomatic()) {
                continue;
            }

            $bankIds = $match->matchLines->pluck('bank_reconciliation_line_id')->all();
            $bookIds = $match->matchLedgerRows->pluck('bank_reconciliation_book_line_id')->all();

            $this->deleteMatchGroup($match);
            $this->resetLinesToUnmatched($bankIds, $bookIds);
            $this->writeAudit($r, 'unmatched', $bankIds, $bookIds, $this->amountsFromTotals(
                (float) $match->bank_total,
                (float) $match->book_total,
                (float) $match->difference,
            ), null, $match->id);

            $removed++;
        }

        return $removed;
    }

    /**
     * @param  array<int, int>  $statementLineIds
     * @param  array<int, int>  $bookLineIds
     */
    public function manualMatch(
        BankReconciliation $r,
        array $statementLineIds,
        array $bookLineIds,
        ?User $user = null,
    ): BankReconciliationMatch {
        return DB::transaction(function () use ($r, $statementLineIds, $bookLineIds, $user): BankReconciliationMatch {
            $this->assertEditable($r);

            if ($statementLineIds === [] || $bookLineIds === []) {
                throw new InvalidArgumentException('Manual match requires at least one statement line and one book line.');
            }

            $this->refreshLineRelations($r);

            $statementLines = $this->resolveStatementLines($r, $statementLineIds);
            $bookLines = $this->resolveBookLines($r, $bookLineIds);

            $bankTotal = $this->sumStatementNet($statementLines);
            $bookTotal = $this->sumBookNet($bookLines);
            $difference = round($bankTotal + $bookTotal, 2);

            if (abs($difference) >= BankReconciliationSupport::TOLERANCE) {
                throw new InvalidArgumentException(sprintf(
                    'Match group does not balance to zero: bank total %s, book total %s, difference %s.',
                    $this->formatMoney($bankTotal),
                    $this->formatMoney($bookTotal),
                    $this->formatMoney($difference),
                ));
            }

            return $this->persistMatch(
                $r,
                $statementLines,
                $bookLines,
                BankMatchType::Manual,
                1.0,
                $bankTotal,
                $bookTotal,
                $difference,
                BankStatementLineStatus::Manual,
                BankBookLineStatus::Manual,
                'matched',
                $user,
            );
        });
    }

    public function unmatchGroup(BankReconciliation $r, BankReconciliationMatch $match, ?User $user = null): void
    {
        DB::transaction(function () use ($r, $match, $user): void {
            $this->assertEditable($r);

            if ($match->bank_reconciliation_id !== $r->id) {
                throw new InvalidArgumentException('This match group does not belong to the given reconciliation.');
            }

            $match->loadMissing(['matchLines', 'matchLedgerRows']);

            $bankIds = $match->matchLines->pluck('bank_reconciliation_line_id')->all();
            $bookIds = $match->matchLedgerRows->pluck('bank_reconciliation_book_line_id')->all();

            $amounts = $this->amountsFromTotals(
                (float) $match->bank_total,
                (float) $match->book_total,
                (float) $match->difference,
            );

            $matchId = $match->id;

            $this->deleteMatchGroup($match);
            $this->resetLinesToUnmatched($bankIds, $bookIds);
            $this->writeAudit($r, 'unmatched', $bankIds, $bookIds, $amounts, $user, $matchId);
        });
    }

    public function excludeStatementLine(
        BankReconciliation $r,
        BankReconciliationLine $line,
        string $reason,
        ?User $user = null,
    ): void {
        DB::transaction(function () use ($r, $line, $reason, $user): void {
            $this->assertEditable($r);
            $this->assertNonBlankReason($reason);
            $this->assertLineBelongsToReconciliation($r, $line);
            $this->assertStatementLineNotInMatch($line);

            $line->update([
                'match_status' => BankStatementLineStatus::Excluded,
                'exclude_reason' => trim($reason),
            ]);

            $this->writeAudit(
                $r,
                'excluded',
                [$line->id],
                [],
                ['net' => $line->netAmount()],
                $user,
                null,
                trim($reason),
            );
        });
    }

    public function excludeBookLine(
        BankReconciliation $r,
        BankReconciliationBookLine $line,
        string $reason,
        ?User $user = null,
    ): void {
        DB::transaction(function () use ($r, $line, $reason, $user): void {
            $this->assertEditable($r);
            $this->assertNonBlankReason($reason);
            $this->assertLineBelongsToReconciliation($r, $line);
            $this->assertBookLineNotInMatch($line);

            $line->update([
                'match_status' => BankBookLineStatus::Excluded,
                'exclude_reason' => trim($reason),
            ]);

            $this->writeAudit(
                $r,
                'excluded',
                [],
                [$line->id],
                ['net' => $line->netAmount()],
                $user,
                null,
                trim($reason),
            );
        });
    }

    public function includeStatementLine(BankReconciliation $r, BankReconciliationLine $line, ?User $user = null): void
    {
        DB::transaction(function () use ($r, $line, $user): void {
            $this->assertEditable($r);
            $this->assertLineBelongsToReconciliation($r, $line);

            $line->update([
                'match_status' => BankStatementLineStatus::Unmatched,
                'exclude_reason' => null,
            ]);

            $this->writeAudit($r, 'included', [$line->id], [], ['net' => $line->netAmount()], $user);
        });
    }

    public function includeBookLine(BankReconciliation $r, BankReconciliationBookLine $line, ?User $user = null): void
    {
        DB::transaction(function () use ($r, $line, $user): void {
            $this->assertEditable($r);
            $this->assertLineBelongsToReconciliation($r, $line);

            $line->update([
                'match_status' => BankBookLineStatus::Unmatched,
                'exclude_reason' => null,
            ]);

            $this->writeAudit($r, 'included', [], [$line->id], ['net' => $line->netAmount()], $user);
        });
    }

    public function markStatementLineOutstanding(
        BankReconciliation $r,
        BankReconciliationLine $line,
        string $note,
        ?User $user = null,
    ): void {
        DB::transaction(function () use ($r, $line, $note, $user): void {
            $this->assertEditable($r);
            $this->assertNonBlankReason($note);
            $this->assertLineBelongsToReconciliation($r, $line);
            $this->assertStatementLineNotInMatch($line);

            $line->update([
                'match_status' => BankStatementLineStatus::Outstanding,
                'line_notes' => trim($note),
            ]);

            $this->writeAudit($r, 'outstanding', [$line->id], [], ['net' => $line->netAmount()], $user, null, trim($note));
        });
    }

    public function markBookLineOutstanding(
        BankReconciliation $r,
        BankReconciliationBookLine $line,
        string $note,
        ?User $user = null,
    ): void {
        DB::transaction(function () use ($r, $line, $note, $user): void {
            $this->assertEditable($r);
            $this->assertNonBlankReason($note);
            $this->assertLineBelongsToReconciliation($r, $line);
            $this->assertBookLineNotInMatch($line);

            $line->update([
                'match_status' => BankBookLineStatus::Outstanding,
                'line_notes' => trim($note),
            ]);

            $this->writeAudit($r, 'outstanding', [], [$line->id], ['net' => $line->netAmount()], $user, null, trim($note));
        });
    }

    public function unmarkStatementLineOutstanding(BankReconciliation $r, BankReconciliationLine $line, ?User $user = null): void
    {
        DB::transaction(function () use ($r, $line, $user): void {
            $this->assertEditable($r);
            $this->assertLineBelongsToReconciliation($r, $line);

            $line->update([
                'match_status' => BankStatementLineStatus::Unmatched,
            ]);

            $this->writeAudit($r, 'outstanding_cleared', [$line->id], [], ['net' => $line->netAmount()], $user);
        });
    }

    public function unmarkBookLineOutstanding(BankReconciliation $r, BankReconciliationBookLine $line, ?User $user = null): void
    {
        DB::transaction(function () use ($r, $line, $user): void {
            $this->assertEditable($r);
            $this->assertLineBelongsToReconciliation($r, $line);

            $line->update([
                'match_status' => BankBookLineStatus::Unmatched,
            ]);

            $this->writeAudit($r, 'outstanding_cleared', [], [$line->id], ['net' => $line->netAmount()], $user);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function suggestMatches(BankReconciliation $r, BankReconciliationLine $line, int $limit = 5): array
    {
        $this->assertLineBelongsToReconciliation($r, $line);

        $r->loadMissing('bookLines');

        $candidates = [];

        foreach ($this->unmatchedBookLines($r) as $bookLine) {
            if (! BankReconciliationSupport::amountsAreOpposite(
                (float) $line->debit,
                (float) $line->credit,
                (float) $bookLine->debit,
                (float) $bookLine->credit,
            )) {
                continue;
            }

            $daysApart = $this->daysApart($line->posting_date, $bookLine->posting_date);
            if ($daysApart > self::FUZZY_DATE_WINDOW_DAYS) {
                continue;
            }

            $similarity = $this->descriptionSimilarityPercent($line->description ?? '', $bookLine->description ?? '');
            $adjusted = $similarity - (2 * $daysApart);
            $score = max(0.0, min(1.0, $adjusted / 100));

            $reason = $this->suggestionReason($line, $bookLine, $daysApart, $similarity);

            $candidates[] = [
                'id' => $bookLine->id,
                'posting_date' => $bookLine->posting_date?->toDateString(),
                'description' => $bookLine->description,
                'reference_number' => $bookLine->reference_number,
                'debit' => round((float) $bookLine->debit, 2),
                'credit' => round((float) $bookLine->credit, 2),
                'net' => $bookLine->netAmount(),
                'score' => round($score, 4),
                'reason' => $reason,
            ];
        }

        usort($candidates, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($candidates, 0, max(1, $limit));
    }

    private function matchByReference(BankReconciliation $r): int
    {
        $created = 0;

        foreach ($this->unmatchedStatementLines($r) as $statementLine) {
            $normalized = BankReconciliationSupport::normalizeReference($statementLine->reference);
            if ($normalized === '') {
                continue;
            }

            $candidate = null;

            foreach ($this->unmatchedBookLines($r) as $bookLine) {
                if (BankReconciliationSupport::normalizeReference($bookLine->reference_number) !== $normalized) {
                    continue;
                }

                if (! BankReconciliationSupport::amountsAreOpposite(
                    (float) $statementLine->debit,
                    (float) $statementLine->credit,
                    (float) $bookLine->debit,
                    (float) $bookLine->credit,
                )) {
                    continue;
                }

                if ($this->daysApart($statementLine->posting_date, $bookLine->posting_date) > self::REFERENCE_DATE_WINDOW_DAYS) {
                    continue;
                }

                $candidate = $bookLine;

                break;
            }

            if ($candidate === null) {
                continue;
            }

            $this->persistAutoPair($r, $statementLine, $candidate, BankMatchType::AutoReference, 1.0);
            $created++;
        }

        return $created;
    }

    private function matchExact(BankReconciliation $r): int
    {
        $created = 0;
        $bookBuckets = $this->bookLinesByOppositeAmount($this->unmatchedBookLines($r));

        foreach ($this->unmatchedStatementLines($r) as $statementLine) {
            $key = $this->oppositeAmountKey(
                (float) $statementLine->debit,
                (float) $statementLine->credit,
            );

            $candidate = null;

            foreach ($bookBuckets[$key] ?? [] as $bookLine) {
                if ($bookLine->match_status !== BankBookLineStatus::Unmatched) {
                    continue;
                }

                if ($this->daysApart($statementLine->posting_date, $bookLine->posting_date) > self::EXACT_DATE_WINDOW_DAYS) {
                    continue;
                }

                $candidate = $bookLine;

                break;
            }

            if ($candidate === null) {
                continue;
            }

            $this->persistAutoPair($r, $statementLine, $candidate, BankMatchType::AutoExact, 1.0);
            $created++;
        }

        return $created;
    }

    private function matchFuzzy(BankReconciliation $r): int
    {
        $created = 0;

        foreach ($this->unmatchedStatementLines($r) as $statementLine) {
            $bestBook = null;
            $bestSimilarity = 0.0;
            $bestAdjusted = -1.0;

            foreach ($this->unmatchedBookLines($r) as $bookLine) {
                if (! BankReconciliationSupport::amountsAreOpposite(
                    (float) $statementLine->debit,
                    (float) $statementLine->credit,
                    (float) $bookLine->debit,
                    (float) $bookLine->credit,
                )) {
                    continue;
                }

                $daysApart = $this->daysApart($statementLine->posting_date, $bookLine->posting_date);
                if ($daysApart > self::FUZZY_DATE_WINDOW_DAYS) {
                    continue;
                }

                $similarity = $this->descriptionSimilarityPercent(
                    $statementLine->description ?? '',
                    $bookLine->description ?? '',
                );
                $adjusted = $similarity - (2 * $daysApart);

                if ($adjusted >= self::FUZZY_MIN_SCORE && $adjusted > $bestAdjusted) {
                    $bestAdjusted = $adjusted;
                    $bestSimilarity = $similarity;
                    $bestBook = $bookLine;
                }
            }

            if ($bestBook === null) {
                continue;
            }

            $confidence = min(0.95, 0.5 + ($bestSimilarity / 200));
            $this->persistAutoPair($r, $statementLine, $bestBook, BankMatchType::AutoFuzzy, $confidence);
            $created++;
        }

        return $created;
    }

    private function matchSplitManyBookToOneBank(BankReconciliation $r): int
    {
        $created = 0;

        foreach ($this->unmatchedStatementLines($r) as $statementLine) {
            $targetSum = round(-1 * $statementLine->netAmount(), 2);
            $candidates = $this->nearestUnmatchedBookLines($r, $statementLine->posting_date);

            $subset = $this->findNetSubset($candidates, $targetSum, self::SPLIT_MIN_LINES, self::SPLIT_MAX_LINES);
            if ($subset === null) {
                continue;
            }

            $this->persistAutoGroup(
                $r,
                collect([$statementLine]),
                collect($subset),
                BankMatchType::AutoSplit,
                0.8,
            );
            $created++;
        }

        return $created;
    }

    private function matchSplitManyBankToOneBook(BankReconciliation $r): int
    {
        $created = 0;

        foreach ($this->unmatchedBookLines($r) as $bookLine) {
            $targetSum = round(-1 * $bookLine->netAmount(), 2);
            $candidates = $this->nearestUnmatchedStatementLines($r, $bookLine->posting_date);

            $subset = $this->findNetSubset($candidates, $targetSum, self::SPLIT_MIN_LINES, self::SPLIT_MAX_LINES);
            if ($subset === null) {
                continue;
            }

            $this->persistAutoGroup(
                $r,
                collect($subset),
                collect([$bookLine]),
                BankMatchType::AutoSplit,
                0.8,
            );
            $created++;
        }

        return $created;
    }

    private function persistAutoPair(
        BankReconciliation $r,
        BankReconciliationLine $statementLine,
        BankReconciliationBookLine $bookLine,
        BankMatchType $type,
        float $confidence,
    ): BankReconciliationMatch {
        $bankTotal = $statementLine->netAmount();
        $bookTotal = $bookLine->netAmount();
        $difference = round($bankTotal + $bookTotal, 2);

        return $this->persistMatch(
            $r,
            collect([$statementLine]),
            collect([$bookLine]),
            $type,
            $confidence,
            $bankTotal,
            $bookTotal,
            $difference,
            BankStatementLineStatus::Matched,
            BankBookLineStatus::Matched,
            'auto_matched',
            null,
        );
    }

    /**
     * @param  Collection<int, BankReconciliationLine>  $statementLines
     * @param  Collection<int, BankReconciliationBookLine>  $bookLines
     */
    private function persistAutoGroup(
        BankReconciliation $r,
        Collection $statementLines,
        Collection $bookLines,
        BankMatchType $type,
        float $confidence,
    ): BankReconciliationMatch {
        $bankTotal = $this->sumStatementNet($statementLines);
        $bookTotal = $this->sumBookNet($bookLines);
        $difference = round($bankTotal + $bookTotal, 2);

        return $this->persistMatch(
            $r,
            $statementLines,
            $bookLines,
            $type,
            $confidence,
            $bankTotal,
            $bookTotal,
            $difference,
            BankStatementLineStatus::Matched,
            BankBookLineStatus::Matched,
            'auto_matched',
            null,
        );
    }

    /**
     * @param  Collection<int, BankReconciliationLine>  $statementLines
     * @param  Collection<int, BankReconciliationBookLine>  $bookLines
     */
    private function persistMatch(
        BankReconciliation $r,
        Collection $statementLines,
        Collection $bookLines,
        BankMatchType $type,
        float $confidence,
        float $bankTotal,
        float $bookTotal,
        float $difference,
        BankStatementLineStatus $statementStatus,
        BankBookLineStatus $bookStatus,
        string $auditAction,
        ?User $user,
    ): BankReconciliationMatch {
        $match = BankReconciliationMatch::query()->create([
            'bank_reconciliation_id' => $r->id,
            'match_type' => $type,
            'confidence_score' => $confidence,
            'bank_total' => round($bankTotal, 2),
            'book_total' => round($bookTotal, 2),
            'difference' => round($difference, 2),
            'created_by' => $user?->id,
        ]);

        foreach ($statementLines as $line) {
            BankReconciliationMatchLine::query()->create([
                'bank_reconciliation_match_id' => $match->id,
                'bank_reconciliation_line_id' => $line->id,
            ]);

            $line->update([
                'match_status' => $statementStatus,
                'is_matched' => true,
                'matched_at' => now(),
            ]);
        }

        foreach ($bookLines as $line) {
            BankReconciliationMatchLedger::query()->create([
                'bank_reconciliation_match_id' => $match->id,
                'bank_reconciliation_book_line_id' => $line->id,
            ]);

            $line->update([
                'match_status' => $bookStatus,
            ]);
        }

        $bankIds = $statementLines->pluck('id')->all();
        $bookIds = $bookLines->pluck('id')->all();

        $this->writeAudit(
            $r,
            $auditAction,
            $bankIds,
            $bookIds,
            $this->amountsFromTotals($bankTotal, $bookTotal, $difference),
            $user,
            $match->id,
        );

        return $match;
    }

    private function deleteMatchGroup(BankReconciliationMatch $match): void
    {
        BankReconciliationMatchLine::query()
            ->where('bank_reconciliation_match_id', $match->id)
            ->delete();

        BankReconciliationMatchLedger::query()
            ->where('bank_reconciliation_match_id', $match->id)
            ->delete();

        $match->delete();
    }

    /**
     * @param  array<int, int>  $bankIds
     * @param  array<int, int>  $bookIds
     */
    private function resetLinesToUnmatched(array $bankIds, array $bookIds): void
    {
        if ($bankIds !== []) {
            BankReconciliationLine::query()
                ->whereIn('id', $bankIds)
                ->update([
                    'match_status' => BankStatementLineStatus::Unmatched,
                    'is_matched' => false,
                    'matched_at' => null,
                ]);
        }

        if ($bookIds !== []) {
            BankReconciliationBookLine::query()
                ->whereIn('id', $bookIds)
                ->update([
                    'match_status' => BankBookLineStatus::Unmatched,
                ]);
        }
    }

    /**
     * @param  array<int, int>  $statementLineIds
     * @return Collection<int, BankReconciliationLine>
     */
    private function resolveStatementLines(BankReconciliation $r, array $statementLineIds): Collection
    {
        $lines = $r->lines->whereIn('id', $statementLineIds);

        if ($lines->count() !== count(array_unique($statementLineIds))) {
            throw new InvalidArgumentException('One or more statement lines do not belong to this reconciliation.');
        }

        foreach ($lines as $line) {
            if ($line->match_status !== BankStatementLineStatus::Unmatched) {
                throw new InvalidArgumentException(sprintf(
                    'Statement line %d cannot be matched because its status is %s.',
                    $line->id,
                    $line->match_status->value,
                ));
            }
        }

        return $lines->values();
    }

    /**
     * @param  array<int, int>  $bookLineIds
     * @return Collection<int, BankReconciliationBookLine>
     */
    private function resolveBookLines(BankReconciliation $r, array $bookLineIds): Collection
    {
        $lines = $r->bookLines->whereIn('id', $bookLineIds);

        if ($lines->count() !== count(array_unique($bookLineIds))) {
            throw new InvalidArgumentException('One or more book lines do not belong to this reconciliation.');
        }

        foreach ($lines as $line) {
            if ($line->match_status !== BankBookLineStatus::Unmatched) {
                throw new InvalidArgumentException(sprintf(
                    'Book line %d cannot be matched because its status is %s.',
                    $line->id,
                    $line->match_status->value,
                ));
            }
        }

        return $lines->values();
    }

    /**
     * @param  Collection<int, BankReconciliationLine>  $lines
     */
    private function sumStatementNet(Collection $lines): float
    {
        return round($lines->sum(fn (BankReconciliationLine $line): float => $line->netAmount()), 2);
    }

    /**
     * @param  Collection<int, BankReconciliationBookLine>  $lines
     */
    private function sumBookNet(Collection $lines): float
    {
        return round($lines->sum(fn (BankReconciliationBookLine $line): float => $line->netAmount()), 2);
    }

    /**
     * @return Collection<int, BankReconciliationLine>
     */
    private function unmatchedStatementLines(BankReconciliation $r): Collection
    {
        $this->refreshLineRelations($r);

        return $r->lines
            ->filter(fn (BankReconciliationLine $line): bool => $line->match_status === BankStatementLineStatus::Unmatched)
            ->values();
    }

    /**
     * @return Collection<int, BankReconciliationBookLine>
     */
    private function unmatchedBookLines(BankReconciliation $r): Collection
    {
        $this->refreshLineRelations($r);

        return $r->bookLines
            ->filter(fn (BankReconciliationBookLine $line): bool => $line->match_status === BankBookLineStatus::Unmatched)
            ->values();
    }

    /**
     * @param  Collection<int, BankReconciliationBookLine>  $bookLines
     * @return array<string, array<int, BankReconciliationBookLine>>
     */
    private function bookLinesByOppositeAmount(Collection $bookLines): array
    {
        $buckets = [];

        foreach ($bookLines as $bookLine) {
            $key = sprintf(
                '%.2f|%.2f',
                (float) $bookLine->debit,
                (float) $bookLine->credit,
            );
            $buckets[$key][] = $bookLine;
        }

        return $buckets;
    }

    private function oppositeAmountKey(float $bankDebit, float $bankCredit): string
    {
        $bookDebit = $bankCredit;
        $bookCredit = $bankDebit;

        return sprintf('%.2f|%.2f', $bookDebit, $bookCredit);
    }

    /**
     * @return array<int, BankReconciliationBookLine>
     */
    private function nearestUnmatchedBookLines(BankReconciliation $r, mixed $anchorDate): array
    {
        $anchor = Carbon::parse($anchorDate);

        return $this->unmatchedBookLines($r)
            ->filter(function (BankReconciliationBookLine $line) use ($anchor): bool {
                return $this->daysApart($anchor, $line->posting_date) <= self::SPLIT_DATE_WINDOW_DAYS;
            })
            ->sortBy(fn (BankReconciliationBookLine $line): int => $this->daysApart($anchor, $line->posting_date))
            ->take(self::SPLIT_MAX_NEARBY_CANDIDATES)
            ->values()
            ->all();
    }

    /**
     * @return array<int, BankReconciliationLine>
     */
    private function nearestUnmatchedStatementLines(BankReconciliation $r, mixed $anchorDate): array
    {
        $anchor = Carbon::parse($anchorDate);

        return $this->unmatchedStatementLines($r)
            ->filter(function (BankReconciliationLine $line) use ($anchor): bool {
                return $this->daysApart($anchor, $line->posting_date) <= self::SPLIT_DATE_WINDOW_DAYS;
            })
            ->sortBy(fn (BankReconciliationLine $line): int => $this->daysApart($anchor, $line->posting_date))
            ->take(self::SPLIT_MAX_NEARBY_CANDIDATES)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, BankReconciliationLine|BankReconciliationBookLine>  $candidates
     * @return array<int, BankReconciliationLine|BankReconciliationBookLine>|null
     */
    private function findNetSubset(array $candidates, float $targetSum, int $minSize, int $maxSize): ?array
    {
        $count = count($candidates);
        if ($count < $minSize) {
            return null;
        }

        $result = $this->searchNetSubset($candidates, 0, [], 0.0, $targetSum, $minSize, $maxSize);
        if ($result === null) {
            return null;
        }

        return array_map(fn (int $index) => $candidates[$index], $result);
    }

    /**
     * @param  array<int, BankReconciliationLine|BankReconciliationBookLine>  $candidates
     * @param  array<int, int>  $picked
     * @return array<int, int>|null
     */
    private function searchNetSubset(
        array $candidates,
        int $start,
        array $picked,
        float $runningSum,
        float $targetSum,
        int $minSize,
        int $maxSize,
    ): ?array {
        if (count($picked) >= $minSize && abs($runningSum - $targetSum) < BankReconciliationSupport::TOLERANCE) {
            return $picked;
        }

        if (count($picked) === $maxSize) {
            return null;
        }

        for ($i = $start; $i < count($candidates); $i++) {
            $line = $candidates[$i];
            $nextSum = round($runningSum + $line->netAmount(), 2);
            $nextPicked = [...$picked, $i];

            $found = $this->searchNetSubset(
                $candidates,
                $i + 1,
                $nextPicked,
                $nextSum,
                $targetSum,
                $minSize,
                $maxSize,
            );

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function descriptionSimilarityPercent(string $left, string $right): float
    {
        $leftNorm = mb_strtolower(trim($left));
        $rightNorm = mb_strtolower(trim($right));

        if ($leftNorm === '' && $rightNorm === '') {
            return 100.0;
        }

        if ($leftNorm === '' || $rightNorm === '') {
            return 0.0;
        }

        similar_text($leftNorm, $rightNorm, $percent);

        return (float) $percent;
    }

    private function suggestionReason(
        BankReconciliationLine $statementLine,
        BankReconciliationBookLine $bookLine,
        int $daysApart,
        float $similarity,
    ): string {
        $normalizedStatement = BankReconciliationSupport::normalizeReference($statementLine->reference);
        $normalizedBook = BankReconciliationSupport::normalizeReference($bookLine->reference_number);

        if ($normalizedStatement !== '' && $normalizedStatement === $normalizedBook) {
            return 'reference number match';
        }

        if ($daysApart === 0) {
            return 'same amount, same date';
        }

        if ($daysApart === 1) {
            return 'same amount, 1 day apart';
        }

        if ($similarity >= self::FUZZY_MIN_SCORE) {
            return sprintf('similar description, %d days apart', $daysApart);
        }

        return sprintf('same amount, %d days apart', $daysApart);
    }

    private function daysApart(mixed $left, mixed $right): int
    {
        return abs(Carbon::parse($left)->diffInDays(Carbon::parse($right), false));
    }

    /**
     * @return array<string, float>
     */
    private function amountsFromTotals(float $bankTotal, float $bookTotal, float $difference): array
    {
        return [
            'bank_total' => round($bankTotal, 2),
            'book_total' => round($bookTotal, 2),
            'difference' => round($difference, 2),
        ];
    }

    /**
     * @param  array<int, int>  $bankLineIds
     * @param  array<int, int>  $bookLineIds
     * @param  array<string, float|int>  $amounts
     */
    private function writeAudit(
        BankReconciliation $r,
        string $action,
        array $bankLineIds,
        array $bookLineIds,
        array $amounts,
        ?User $user = null,
        ?int $matchId = null,
        ?string $notes = null,
    ): void {
        BankReconciliationAudit::query()->create([
            'bank_reconciliation_id' => $r->id,
            'bank_reconciliation_match_id' => $matchId,
            'action' => $action,
            'bank_line_ids' => $bankLineIds,
            'book_line_ids' => $bookLineIds,
            'amounts' => $amounts,
            'performed_by' => $user?->id ?? auth()->id(),
            'notes' => $notes,
        ]);
    }

    private function assertEditable(BankReconciliation $r): void
    {
        if ($r->isLockedForEditing()) {
            throw new InvalidArgumentException(
                'This bank reconciliation is locked for editing and cannot be changed in its current status.',
            );
        }
    }

    private function assertNonBlankReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required for this action.');
        }
    }

    private function assertLineBelongsToReconciliation(
        BankReconciliation $r,
        BankReconciliationLine|BankReconciliationBookLine $line,
    ): void {
        if ($line->bank_reconciliation_id !== $r->id) {
            throw new InvalidArgumentException('The line does not belong to this reconciliation.');
        }
    }

    private function assertStatementLineNotInMatch(BankReconciliationLine $line): void
    {
        if ($line->match_status !== BankStatementLineStatus::Unmatched) {
            throw new InvalidArgumentException(sprintf(
                'Statement line %d is already %s. Unmatch or include it before changing its status.',
                $line->id,
                $line->match_status->value,
            ));
        }

        if (BankReconciliationMatchLine::query()->where('bank_reconciliation_line_id', $line->id)->exists()) {
            throw new InvalidArgumentException(sprintf(
                'Statement line %d is part of a match group. Unmatch it first.',
                $line->id,
            ));
        }
    }

    private function assertBookLineNotInMatch(BankReconciliationBookLine $line): void
    {
        if ($line->match_status !== BankBookLineStatus::Unmatched) {
            throw new InvalidArgumentException(sprintf(
                'Book line %d is already %s. Unmatch or include it before changing its status.',
                $line->id,
                $line->match_status->value,
            ));
        }

        if (BankReconciliationMatchLedger::query()->where('bank_reconciliation_book_line_id', $line->id)->exists()) {
            throw new InvalidArgumentException(sprintf(
                'Book line %d is part of a match group. Unmatch it first.',
                $line->id,
            ));
        }
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function refreshLineRelations(BankReconciliation $r): void
    {
        $r->unsetRelation('lines');
        $r->unsetRelation('bookLines');
        $r->load(['lines', 'bookLines']);
    }

    private function refreshMatchRelations(BankReconciliation $r): void
    {
        $r->unsetRelation('matches');
        $r->load(['matches.matchLines', 'matches.matchLedgerRows']);
    }
}
