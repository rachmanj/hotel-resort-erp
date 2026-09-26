<?php

namespace App\Console\Commands;

use App\Enums\BankReconciliationStatus;
use App\Enums\BankStatementLineStatus;
use App\Models\BankReconciliation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeBankReconciliationSessionsCommand extends Command
{
    /**
     * Safety cap: refuse a live purge above this many eligible sessions unless --force is passed.
     */
    public const SAFETY_THRESHOLD = 50;

    protected $signature = 'bankrec:purge-sessions
                            {--days=30 : Delete draft/failed sessions older than this many days}
                            {--dry-run : List what would be deleted without deleting}
                            {--force : Allow deleting more than '.self::SAFETY_THRESHOLD.' sessions in one run}';

    protected $description = 'Delete old draft/failed bank reconciliation sessions that never touched matching or GL adjustments';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $cutoff = now()->subDays($days);

        $candidates = BankReconciliation::query()
            ->whereIn('status', [
                BankReconciliationStatus::Draft->value,
                BankReconciliationStatus::Failed->value,
            ])
            ->where('updated_at', '<', $cutoff)
            ->orderBy('id')
            ->get();

        $eligible = [];
        $skipped = [];

        foreach ($candidates as $session) {
            $reason = $this->skipReason($session);

            if ($reason !== null) {
                $skipped[] = ['id' => $session->id, 'reason' => $reason];

                continue;
            }

            $eligible[] = $session;
        }

        $eligibleCount = count($eligible);

        if (! $dryRun && $eligibleCount > self::SAFETY_THRESHOLD && ! $force) {
            $this->error(sprintf(
                '%d session(s) would be deleted, which exceeds the safety threshold of %d. Re-run with --dry-run to review, or pass --force if intentional.',
                $eligibleCount,
                self::SAFETY_THRESHOLD,
            ));

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info(sprintf(
                'Dry run: %d session(s) would be deleted (cutoff %s, older than %d days). %d skipped.',
                $eligibleCount,
                $cutoff->toDateTimeString(),
                $days,
                count($skipped),
            ));
        } else {
            $deleted = 0;

            foreach ($eligible as $session) {
                DB::transaction(function () use ($session): void {
                    $session->delete();
                });
                $deleted++;
            }

            $this->info(sprintf(
                'Deleted %d session(s) (cutoff %s, older than %d days). %d skipped.',
                $deleted,
                $cutoff->toDateTimeString(),
                $days,
                count($skipped),
            ));
        }

        foreach ($skipped as $row) {
            $this->line("  Skip session #{$row['id']}: {$row['reason']}");
        }

        return self::SUCCESS;
    }

    private function skipReason(BankReconciliation $session): ?string
    {
        $hasMatchedStatementLine = $session->lines()
            ->where('match_status', '!=', BankStatementLineStatus::Unmatched->value)
            ->exists();

        if ($hasMatchedStatementLine) {
            return 'has statement line that is matched, excluded, outstanding, or adjusted';
        }

        if ($session->matches()->exists()) {
            return 'has match groups';
        }

        if ($session->lines()->whereNotNull('adjusting_journal_id')->exists()) {
            return 'has bank adjustment journals';
        }

        return null;
    }
}
