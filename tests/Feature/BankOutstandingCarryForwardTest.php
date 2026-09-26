<?php

namespace Tests\Feature;

use App\Actions\Accounting\CarryForwardOutstandingAction;
use App\Enums\BankAccountType;
use App\Enums\BankBookLineStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BankOutstandingCarryForwardTest extends TestCase
{
    use RefreshDatabase;

    private CarryForwardOutstandingAction $action;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(CarryForwardOutstandingAction::class);

        $hotel = Hotel::query()->create([
            'name' => 'Carry Forward Hotel',
            'code' => 'CFH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $hotel->id]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $hotel->id,
            'account_code' => '1-1300',
            'name' => 'Bank',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '1122334455',
            'account_name' => 'Main',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);
    }

    public function test_outstanding_book_line_carried_to_next_session_with_provenance(): void
    {
        $august = $this->makePeriodSession('2026-08-01', '2026-08-31');
        $september = $this->makePeriodSession('2026-09-01', '2026-09-30');

        $outstanding = BankReconciliationBookLine::factory()->outstanding()->create([
            'bank_reconciliation_id' => $august->id,
            'posting_date' => '2026-08-20',
            'debit' => 150_000,
            'credit' => 0,
            'description' => 'Uncleared deposit',
            'general_ledger_id' => null,
        ]);

        $imported = $this->action->importOutstandingInto($september);

        $this->assertSame(1, $imported);

        $carried = BankReconciliationBookLine::query()
            ->where('bank_reconciliation_id', $september->id)
            ->where('is_carried_forward', true)
            ->first();

        $this->assertNotNull($carried);
        $this->assertSame(BankBookLineStatus::Unmatched, $carried->match_status);
        $this->assertSame($outstanding->id, $carried->carried_from_book_line_id);
        $this->assertSame($august->id, $carried->origin_reconciliation_id);
        $this->assertStringContainsString('Aug 2026', (string) $carried->line_notes);

        $outstanding->refresh();
        $this->assertSame(BankBookLineStatus::Outstanding, $outstanding->match_status);
    }

    public function test_carry_forward_is_idempotent(): void
    {
        $august = $this->makePeriodSession('2026-08-01', '2026-08-31');
        $september = $this->makePeriodSession('2026-09-01', '2026-09-30');

        BankReconciliationBookLine::factory()->outstanding()->create([
            'bank_reconciliation_id' => $august->id,
            'posting_date' => '2026-08-15',
            'debit' => 50_000,
            'credit' => 0,
        ]);

        $first = $this->action->importOutstandingInto($september);
        $second = $this->action->importOutstandingInto($september);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);
        $this->assertSame(1, BankReconciliationBookLine::query()->where('bank_reconciliation_id', $september->id)->count());
    }

    public function test_cleared_outstanding_does_not_carry_into_third_session(): void
    {
        $august = $this->makePeriodSession('2026-08-01', '2026-08-31');
        $september = $this->makePeriodSession('2026-09-01', '2026-09-30');
        $october = $this->makePeriodSession('2026-10-01', '2026-10-31');

        $augustOutstanding = BankReconciliationBookLine::factory()->outstanding()->create([
            'bank_reconciliation_id' => $august->id,
            'posting_date' => '2026-08-22',
            'debit' => 90_000,
            'credit' => 0,
        ]);

        $this->action->importOutstandingInto($september);

        $septemberCarried = BankReconciliationBookLine::query()
            ->where('bank_reconciliation_id', $september->id)
            ->where('carried_from_book_line_id', $augustOutstanding->id)
            ->firstOrFail();

        $septemberCarried->update(['match_status' => BankBookLineStatus::Matched]);

        $importedToOctober = $this->action->importOutstandingInto($october);

        $this->assertSame(0, $importedToOctober);
        $this->assertFalse(
            BankReconciliationBookLine::query()
                ->where('bank_reconciliation_id', $october->id)
                ->where('carried_from_book_line_id', $augustOutstanding->id)
                ->exists()
        );
    }

    public function test_outstanding_needing_attention_filters_by_age(): void
    {
        Carbon::setTestNow('2026-09-26 10:00:00');

        $reconciliation = $this->makePeriodSession('2026-09-01', '2026-09-30');

        $oldOrigin = $this->makePeriodSession('2026-06-01', '2026-06-30');

        BankReconciliationLine::factory()->outstanding()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-05',
            'debit' => 0,
            'credit' => 100_000,
            'description' => 'Old uncleared credit',
            'origin_reconciliation_id' => $oldOrigin->id,
        ]);

        BankReconciliationBookLine::factory()->outstanding()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-10',
            'debit' => 20_000,
            'credit' => 0,
            'description' => 'Recent outstanding',
            'origin_reconciliation_id' => $reconciliation->id,
        ]);

        $attention = $this->action->outstandingNeedingAttention($reconciliation, 60);

        $this->assertCount(1, $attention);
        $this->assertSame('Old uncleared credit', $attention->first()['description']);
        $this->assertGreaterThan(60, $attention->first()['days_outstanding']);
        $this->assertSame('Jun 2026', $attention->first()['origin_period']);

        Carbon::setTestNow();
    }

    private function makePeriodSession(string $periode, string $periodEnd): BankReconciliation
    {
        return BankReconciliation::factory()
            ->for($this->bankAccount)
            ->create([
                'periode' => $periode,
                'period_end_date' => $periodEnd,
            ]);
    }
}
