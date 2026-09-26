<?php

namespace Tests\Feature;

use App\Actions\Accounting\PostBankAdjustmentAction;
use App\Actions\Accounting\ReverseBankAdjustmentAction;
use App\Enums\BankAccountType;
use App\Enums\BankReconciliationStatus;
use App\Enums\BankStatementLineStatus;
use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationAudit;
use App\Models\BankReconciliationLine;
use App\Models\BankReconciliationMatch;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedger;
use App\Models\Hotel;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BankAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private ChartOfAccount $bankCoa;

    private ChartOfAccount $bankChargesCoa;

    private ChartOfAccount $jasaGiroCoa;

    private BankAccount $bankAccount;

    private AccountingPeriod $openPeriod;

    private User $user;

    private PostBankAdjustmentAction $postAdjustment;

    private ReverseBankAdjustmentAction $reverseAdjustment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->postAdjustment = app(PostBankAdjustmentAction::class);
        $this->reverseAdjustment = app(ReverseBankAdjustmentAction::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Adjustment Hotel',
            'code' => 'ADJ',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $this->hotel->id]);

        $this->bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '1-1300',
            'name' => 'Bank GL',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankChargesCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '6-8600',
            'name' => 'Bank Charges',
            'account_type' => 'expense',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->jasaGiroCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '4-9100',
            'name' => 'Pendapatan Jasa Giro',
            'account_type' => 'revenue',
            'normal_balance' => 'credit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '9988776655',
            'account_name' => 'Operating',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $this->bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $this->openPeriod = AccountingPeriod::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => '2026-09',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);

        $this->user = User::factory()->create(['hotel_id' => $this->hotel->id]);
    }

    public function test_bank_charge_posts_counter_debit_and_bank_credit(): void
    {
        $reconciliation = $this->makeReconciliation();
        $line = $this->unmatchedStatementLine($reconciliation, [
            'posting_date' => '2026-09-12',
            'debit' => 6_500,
            'credit' => 0,
            'description' => 'BIAYA ADMINISTRASI',
        ]);

        $entry = ($this->postAdjustment)(
            $reconciliation,
            $line,
            $this->bankChargesCoa->id,
            'Monthly bank admin fee',
            $this->user,
        );

        $this->assertStringStartsWith('JV-', $entry->journal_no);
        $this->assertSame(JournalEntryStatus::Posted, $entry->status);
        $this->assertNull($entry->reversed_from_id);

        $lines = $entry->lines()->with('chartOfAccount')->get();
        $this->assertCount(2, $lines);

        $counterLine = $lines->firstWhere('chart_of_account_id', $this->bankChargesCoa->id);
        $bankLine = $lines->firstWhere('chart_of_account_id', $this->bankCoa->id);

        $this->assertSame(6_500.0, (float) $counterLine->debit);
        $this->assertSame(0.0, (float) $counterLine->credit);
        $this->assertSame(0.0, (float) $bankLine->debit);
        $this->assertSame(6_500.0, (float) $bankLine->credit);

        $line->refresh();
        $this->assertSame($entry->id, $line->adjusting_journal_id);

        $glCounter = GeneralLedger::query()
            ->where('chart_of_account_id', $this->bankChargesCoa->id)
            ->where('source_type', 'journal_entry')
            ->where('source_id', $entry->id)
            ->first();

        $glBank = GeneralLedger::query()
            ->where('chart_of_account_id', $this->bankCoa->id)
            ->where('source_type', 'journal_entry')
            ->where('source_id', $entry->id)
            ->first();

        $this->assertNotNull($glCounter);
        $this->assertNotNull($glBank);
        $this->assertSame(6_500.0, (float) $glCounter->debit);
        $this->assertSame(6_500.0, (float) $glBank->credit);
    }

    public function test_bank_interest_posts_bank_debit_and_counter_credit(): void
    {
        $reconciliation = $this->makeReconciliation();
        $line = $this->unmatchedStatementLine($reconciliation, [
            'posting_date' => '2026-09-18',
            'debit' => 0,
            'credit' => 25_000,
            'description' => 'BUNGA / JASA GIRO',
        ]);

        $entry = ($this->postAdjustment)(
            $reconciliation,
            $line,
            $this->jasaGiroCoa->id,
            'Interest income',
            $this->user,
        );

        $lines = $entry->lines;
        $bankLine = $lines->firstWhere('chart_of_account_id', $this->bankCoa->id);
        $counterLine = $lines->firstWhere('chart_of_account_id', $this->jasaGiroCoa->id);

        $this->assertSame(25_000.0, (float) $bankLine->debit);
        $this->assertSame(0.0, (float) $bankLine->credit);
        $this->assertSame(0.0, (float) $counterLine->debit);
        $this->assertSame(25_000.0, (float) $counterLine->credit);
    }

    public function test_second_adjustment_and_non_unmatched_lines_are_refused(): void
    {
        $reconciliation = $this->makeReconciliation();
        $line = $this->unmatchedStatementLine($reconciliation, [
            'posting_date' => '2026-09-10',
            'debit' => 1_000,
            'credit' => 0,
        ]);

        ($this->postAdjustment)($reconciliation, $line, $this->bankChargesCoa->id, 'First', $this->user);

        $line->refresh();

        try {
            ($this->postAdjustment)($reconciliation, $line, $this->bankChargesCoa->id, 'Second', $this->user);
            $this->fail('Expected second adjustment to be refused.');
        } catch (InvalidArgumentException $exception) {
            $message = strtolower($exception->getMessage());
            $this->assertTrue(
                str_contains($message, 'already has')
                || str_contains($message, 'unmatched'),
                $exception->getMessage(),
            );
        }

        $excluded = BankReconciliationLine::factory()->excluded()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-11',
            'debit' => 500,
            'credit' => 0,
        ]);

        try {
            ($this->postAdjustment)($reconciliation, $excluded, $this->bankChargesCoa->id, 'Excluded', $this->user);
            $this->fail('Expected excluded line adjustment to be refused.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('unmatched', strtolower($exception->getMessage()));
        }

        $matched = BankReconciliationLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-12',
            'debit' => 700,
            'credit' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        ($this->postAdjustment)($reconciliation, $matched, $this->bankChargesCoa->id, 'Matched', $this->user);
    }

    public function test_adjustment_on_locked_reconciliation_posts_nothing(): void
    {
        $reconciliation = $this->makeReconciliation(['status' => BankReconciliationStatus::PendingValidation]);
        $line = $this->unmatchedStatementLine($reconciliation, [
            'posting_date' => '2026-09-14',
            'debit' => 2_000,
            'credit' => 0,
        ]);

        $glCountBefore = GeneralLedger::query()->count();

        try {
            ($this->postAdjustment)($reconciliation, $line, $this->bankChargesCoa->id, 'Locked', $this->user);
            $this->fail('Expected locked reconciliation to be refused.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('locked', strtolower($exception->getMessage()));
        }

        $this->assertSame($glCountBefore, GeneralLedger::query()->count());
        $this->assertSame(0, JournalEntry::query()->count());
    }

    public function test_closed_accounting_period_refuses_adjustment(): void
    {
        $this->openPeriod->update(['status' => 'closed']);

        $reconciliation = $this->makeReconciliation();
        $line = $this->unmatchedStatementLine($reconciliation, [
            'posting_date' => '2026-09-16',
            'debit' => 3_000,
            'credit' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is closed');

        try {
            ($this->postAdjustment)($reconciliation, $line, $this->bankChargesCoa->id, 'Closed period', $this->user);
        } finally {
            $this->assertSame(0, JournalEntry::query()->count());
            $this->assertSame(0, GeneralLedger::query()->where('source_type', 'journal_entry')->count());
        }
    }

    public function test_reversal_zeros_net_gl_and_clears_adjusting_journal(): void
    {
        $reconciliation = $this->makeReconciliation();
        $line = $this->unmatchedStatementLine($reconciliation, [
            'posting_date' => '2026-09-08',
            'debit' => 4_500,
            'credit' => 0,
        ]);

        $original = ($this->postAdjustment)(
            $reconciliation,
            $line,
            $this->bankChargesCoa->id,
            'Fee to reverse',
            $this->user,
        );

        $reversal = ($this->reverseAdjustment)($reconciliation, $line->fresh(), $this->user);

        $this->assertSame($original->id, $reversal->reversed_from_id);

        $counterNet = GeneralLedger::query()
            ->whereIn('chart_of_account_id', [$this->bankChargesCoa->id, $this->bankCoa->id])
            ->selectRaw('SUM(debit) - SUM(credit) as net')
            ->value('net');

        $this->assertSame(0.0, (float) $counterNet);

        $line->refresh();
        $this->assertNull($line->adjusting_journal_id);

        $this->assertTrue(
            BankReconciliationAudit::query()
                ->where('bank_reconciliation_id', $reconciliation->id)
                ->where('action', 'adjustment_reversed')
                ->exists()
        );

        $this->assertSame(2, JournalEntry::query()->count());
        $this->assertNotNull(JournalEntry::query()->find($original->id));
    }

    public function test_successful_adjustment_triggers_auto_match(): void
    {
        $reconciliation = $this->makeReconciliation();
        $line = $this->unmatchedStatementLine($reconciliation, [
            'posting_date' => '2026-09-20',
            'debit' => 8_800,
            'credit' => 0,
        ]);

        ($this->postAdjustment)($reconciliation, $line, $this->bankChargesCoa->id, 'Auto-match fee', $this->user);

        $line->refresh();

        $this->assertNotSame(BankStatementLineStatus::Unmatched, $line->match_status);

        $hasMatchGroup = BankReconciliationMatch::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->exists();

        $this->assertTrue($hasMatchGroup || $line->match_status === BankStatementLineStatus::Matched);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeReconciliation(array $attributes = []): BankReconciliation
    {
        return BankReconciliation::factory()
            ->for($this->bankAccount)
            ->create(array_merge([
                'periode' => '2026-09-01',
                'period_end_date' => '2026-09-30',
            ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function unmatchedStatementLine(BankReconciliation $reconciliation, array $overrides = []): BankReconciliationLine
    {
        return BankReconciliationLine::factory()->create(array_merge([
            'bank_reconciliation_id' => $reconciliation->id,
            'match_status' => BankStatementLineStatus::Unmatched,
        ], $overrides));
    }
}
