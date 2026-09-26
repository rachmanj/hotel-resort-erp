<?php

namespace Tests\Feature;

use App\Console\Commands\PurgeBankReconciliationSessionsCommand;
use App\Enums\BankAccountType;
use App\Enums\BankReconciliationStatus;
use App\Enums\BankStatementLineStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationLine;
use App\Models\BankReconciliationMatch;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeBankReconciliationSessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    private static int $draftPeriodSequence = 1;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $hotel = Hotel::query()->create([
            'name' => 'Purge Hotel',
            'code' => 'PUH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $hotel->id,
            'account_code' => '1-1311',
            'name' => 'Purge Bank',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '0001112222',
            'account_name' => 'Purge',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);
    }

    public function test_dry_run_lists_eligible_sessions_without_deleting(): void
    {
        $eligible = $this->oldDraftSession();
        BankReconciliation::query()
            ->whereKey($eligible->id)
            ->update(['updated_at' => now()->subDays(40)]);

        $this->artisan('bankrec:purge-sessions', ['--days' => 30, '--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Dry run: 1 session(s) would be deleted');

        $this->assertDatabaseHas('bank_reconciliations', ['id' => $eligible->id]);
    }

    public function test_purge_deletes_only_eligible_draft_sessions(): void
    {
        $eligible = $this->oldDraftSession();
        BankReconciliation::query()
            ->whereKey($eligible->id)
            ->update(['updated_at' => now()->subDays(40)]);

        $withMatch = $this->oldDraftSession();
        BankReconciliation::query()
            ->whereKey($withMatch->id)
            ->update(['updated_at' => now()->subDays(40)]);
        BankReconciliationMatch::factory()->create(['bank_reconciliation_id' => $withMatch->id]);

        $recent = $this->oldDraftSession();
        BankReconciliation::query()
            ->whereKey($recent->id)
            ->update(['updated_at' => now()->subDays(5)]);

        $this->artisan('bankrec:purge-sessions', ['--days' => 30])
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted 1 session(s)');

        $this->assertDatabaseMissing('bank_reconciliations', ['id' => $eligible->id]);
        $this->assertDatabaseHas('bank_reconciliations', ['id' => $withMatch->id]);
        $this->assertDatabaseHas('bank_reconciliations', ['id' => $recent->id]);
    }

    public function test_purge_refuses_large_batch_without_force(): void
    {
        $threshold = PurgeBankReconciliationSessionsCommand::SAFETY_THRESHOLD;

        for ($i = 0; $i < $threshold + 1; $i++) {
            $session = $this->oldDraftSession();
            BankReconciliation::query()
                ->whereKey($session->id)
                ->update(['updated_at' => now()->subDays(40)]);
        }

        $this->artisan('bankrec:purge-sessions', ['--days' => 30])
            ->assertFailed();

        $this->assertSame($threshold + 1, BankReconciliation::query()->count());
    }

    public function test_purge_skips_session_with_matched_statement_line(): void
    {
        $session = $this->oldDraftSession();
        BankReconciliation::query()
            ->whereKey($session->id)
            ->update(['updated_at' => now()->subDays(40)]);

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $session->id,
            'match_status' => BankStatementLineStatus::Manual,
        ]);

        $this->artisan('bankrec:purge-sessions', ['--days' => 30])
            ->assertSuccessful()
            ->expectsOutputToContain('Deleted 0 session(s)');

        $this->assertDatabaseHas('bank_reconciliations', ['id' => $session->id]);
    }

    private function oldDraftSession(): BankReconciliation
    {
        $month = self::$draftPeriodSequence++;

        return BankReconciliation::factory()->for($this->bankAccount)->create([
            'status' => BankReconciliationStatus::Draft,
            'period_end_date' => sprintf('2025-%02d-28', $month),
            'periode' => sprintf('2025-%02d-01', $month),
        ]);
    }
}
