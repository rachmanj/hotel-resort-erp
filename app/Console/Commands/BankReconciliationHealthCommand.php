<?php

namespace App\Console\Commands;

use App\Actions\Accounting\CarryForwardOutstandingAction;
use App\Enums\BankReconciliationStatus;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Services\Accounting\BankReconciliation\BankBookLineFetcher;
use App\Services\Accounting\BankReconciliation\BankReconciliationBalanceService;
use Illuminate\Console\Command;

class BankReconciliationHealthCommand extends Command
{
    protected $signature = 'bankrec:health';

    protected $description = 'Read-only health report for bank reconciliation data integrity';

    public function handle(
        BankReconciliationBalanceService $balanceService,
        BankBookLineFetcher $bookLineFetcher,
        CarryForwardOutstandingAction $carryForwardOutstandingAction,
    ): int {
        $anomalies = 0;

        $orphans = BankReconciliationMatch::query()
            ->withCount(['matchLines', 'matchLedgerRows'])
            ->get()
            ->filter(fn (BankReconciliationMatch $match): bool => $match->match_lines_count === 0
                || $match->match_ledger_rows_count === 0);

        if ($orphans->isNotEmpty()) {
            $anomalies += $orphans->count();
            $this->warn('Orphan match groups (empty bank or book side):');
            foreach ($orphans as $match) {
                $this->line(sprintf(
                    '  Match #%d (session #%d): %d statement line(s), %d book line(s)',
                    $match->id,
                    $match->bank_reconciliation_id,
                    $match->match_lines_count,
                    $match->match_ledger_rows_count,
                ));
            }
        } else {
            $this->info('Orphan match groups: none');
        }

        $unbalancedCompleted = BankReconciliation::query()
            ->where('status', BankReconciliationStatus::Completed->value)
            ->get()
            ->filter(function (BankReconciliation $session) use ($balanceService): bool {
                $session->load(['lines', 'bookLines']);

                return ! $balanceService->isBalanced($session);
            });

        if ($unbalancedCompleted->isNotEmpty()) {
            $anomalies += $unbalancedCompleted->count();
            $this->warn('Completed sessions that fail the balance proof:');
            foreach ($unbalancedCompleted as $session) {
                $session->load(['lines', 'bookLines']);
                $this->line(sprintf(
                    '  Session #%d (%s): %s',
                    $session->id,
                    $session->period_end_date?->toDateString() ?? 'n/a',
                    $balanceService->diagnostic($session),
                ));
            }
        } else {
            $this->info('Completed sessions with balance proof: all pass');
        }

        $staleSessions = BankReconciliation::query()
            ->whereNotIn('status', [
                BankReconciliationStatus::Completed->value,
                BankReconciliationStatus::Void->value,
            ])
            ->get()
            ->filter(fn (BankReconciliation $session): bool => $bookLineFetcher->staleLines($session)->isNotEmpty());

        if ($staleSessions->isNotEmpty()) {
            $anomalies += $staleSessions->count();
            $this->warn('Sessions with stale book lines:');
            foreach ($staleSessions as $session) {
                $staleCount = $bookLineFetcher->staleLines($session)->count();
                $this->line(sprintf(
                    '  Session #%d (%s): %d stale book line(s)',
                    $session->id,
                    $session->period_end_date?->toDateString() ?? 'n/a',
                    $staleCount,
                ));
            }
        } else {
            $this->info('Stale book lines: none');
        }

        $agedCarryForwards = 0;
        $sessionsWithCarry = BankReconciliation::query()
            ->where(function ($query): void {
                $query->whereHas('lines', fn ($q) => $q->where('is_carried_forward', true))
                    ->orWhereHas('bookLines', fn ($q) => $q->where('is_carried_forward', true));
            })
            ->get();

        if ($sessionsWithCarry->isNotEmpty()) {
            $this->line('Carried-forward items older than 60 days:');
            foreach ($sessionsWithCarry as $session) {
                $items = $carryForwardOutstandingAction->outstandingNeedingAttention($session, 60);
                if ($items->isEmpty()) {
                    continue;
                }

                foreach ($items as $item) {
                    $agedCarryForwards++;
                    $anomalies++;
                    $this->line(sprintf(
                        '  Session #%d line #%d: %s — %s days outstanding (origin %s)',
                        $session->id,
                        $item['id'],
                        $item['description'] ?? '(no description)',
                        $item['days_outstanding'],
                        $item['origin_period'] ?? 'unknown',
                    ));
                }
            }
        }

        if ($agedCarryForwards === 0) {
            $this->info('Carried-forward items older than 60 days: none');
        }

        if ($anomalies === 0) {
            $this->info('Bank reconciliation health: clean');

            return self::SUCCESS;
        }

        $this->error(sprintf('Bank reconciliation health: %d anomaly/anomalies found', $anomalies));

        return self::FAILURE;
    }
}
