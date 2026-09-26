<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\BankBookLineStatus;
use App\Enums\BankReconciliationStatus;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedger;
use App\Models\Hotel;
use App\Services\Accounting\BankReconciliation\BankBookLineFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BankBookLineFetchTest extends TestCase
{
    use RefreshDatabase;

    private BankBookLineFetcher $fetcher;

    private Hotel $hotel;

    private ChartOfAccount $bankCoa;

    private ChartOfAccount $otherCoa;

    private BankAccount $bankAccount;

    private AccountingPeriod $accountingPeriod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fetcher = app(BankBookLineFetcher::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Book Fetch Hotel',
            'code' => 'BFH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $this->hotel->id]);

        $this->bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '1-1300',
            'name' => 'Operating Bank GL',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->otherCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '1-1400',
            'name' => 'Other Bank GL',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '1111222233',
            'account_name' => 'Main Operating',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $this->bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $this->accountingPeriod = AccountingPeriod::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => '2026-09',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);
    }

    public function test_fetch_creates_unmatched_book_lines_and_book_balances_from_gl(): void
    {
        $reconciliation = $this->makeReconciliation();

        $this->createGlEntry([
            'transaction_date' => '2026-08-15',
            'debit' => 500_000,
            'credit' => 0,
            'description' => 'Opening activity',
            'reference_number' => 'GL-PRE',
        ]);

        $this->createGlEntry([
            'transaction_date' => '2026-09-10',
            'debit' => 100_000,
            'credit' => 0,
            'description' => 'September debit',
            'reference_number' => 'GL-IN-1',
        ]);

        $this->createGlEntry([
            'transaction_date' => '2026-09-20',
            'debit' => 0,
            'credit' => 50_000,
            'description' => 'September credit',
            'reference_number' => 'GL-IN-2',
        ]);

        $count = $this->fetcher->fetchAndReplace($reconciliation);

        $this->assertSame(2, $count);

        $reconciliation->refresh();
        $this->assertSame(500_000.0, (float) $reconciliation->book_opening_balance);
        $this->assertSame(550_000.0, (float) $reconciliation->book_closing_balance);

        $lines = BankReconciliationBookLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->orderBy('posting_date')
            ->get();

        $this->assertCount(2, $lines);
        $this->assertTrue($lines->every(fn (BankReconciliationBookLine $line): bool => $line->match_status === BankBookLineStatus::Unmatched));
        $this->assertSame('GL-IN-1', $lines[0]->reference_number);
        $this->assertFalse((bool) $lines[0]->is_stale);
    }

    public function test_fetch_scopes_gl_rows_to_bank_coa_and_hotel(): void
    {
        $reconciliation = $this->makeReconciliation();

        $this->createGlEntry([
            'transaction_date' => '2026-09-05',
            'debit' => 200_000,
            'credit' => 0,
        ]);

        $this->createGlEntry([
            'chart_of_account_id' => $this->otherCoa->id,
            'transaction_date' => '2026-09-06',
            'debit' => 300_000,
            'credit' => 0,
        ]);

        $otherHotel = Hotel::query()->create([
            'name' => 'Other Hotel',
            'code' => 'OTH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $otherPeriod = AccountingPeriod::query()->create([
            'hotel_id' => $otherHotel->id,
            'name' => '2026-09',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);

        GeneralLedger::query()->withoutGlobalScope('hotel')->create([
            'hotel_id' => $otherHotel->id,
            'chart_of_account_id' => $this->bankCoa->id,
            'accounting_period_id' => $otherPeriod->id,
            'transaction_date' => '2026-09-07',
            'debit' => 400_000,
            'credit' => 0,
            'description' => 'Wrong hotel',
            'reference_number' => 'GL-OTHER-HOTEL',
            'source_type' => 'test',
            'source_id' => 1,
        ]);

        $count = $this->fetcher->fetchAndReplace($reconciliation);

        $this->assertSame(1, $count);
        $this->assertSame(1, BankReconciliationBookLine::query()->where('bank_reconciliation_id', $reconciliation->id)->count());
    }

    public function test_second_fetch_is_idempotent_and_preserves_manual_and_matched_lines(): void
    {
        $reconciliation = $this->makeReconciliation();

        $inPeriodGl = $this->createGlEntry([
            'transaction_date' => '2026-09-12',
            'debit' => 1_000_000,
            'credit' => 0,
            'reference_number' => 'GL-MANUAL',
        ]);

        $beforePeriodGl = $this->createGlEntry([
            'transaction_date' => '2026-08-20',
            'debit' => 750_000,
            'credit' => 0,
            'reference_number' => 'GL-MATCHED-OLD',
        ]);

        $this->fetcher->fetchAndReplace($reconciliation);

        $manualLine = BankReconciliationBookLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('general_ledger_id', $inPeriodGl->id)
            ->firstOrFail();
        $manualLine->update(['match_status' => BankBookLineStatus::Manual]);

        $matchedLine = BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'general_ledger_id' => $beforePeriodGl->id,
            'posting_date' => '2026-09-18',
            'debit' => 750_000,
            'credit' => 0,
            'reference_number' => 'GL-MATCHED-OLD',
            'match_status' => BankBookLineStatus::Matched,
        ]);

        $countAfterSecondFetch = $this->fetcher->fetchAndReplace($reconciliation);

        $this->assertSame(2, $countAfterSecondFetch);
        $this->assertSame(2, BankReconciliationBookLine::query()->where('bank_reconciliation_id', $reconciliation->id)->count());

        $manualLine->refresh();
        $matchedLine->refresh();

        $this->assertSame(BankBookLineStatus::Manual, $manualLine->match_status);
        $this->assertSame(BankBookLineStatus::Matched, $matchedLine->match_status);
    }

    public function test_refresh_stale_flags_when_gl_row_deleted(): void
    {
        $reconciliation = $this->makeReconciliation();

        $gl = $this->createGlEntry([
            'transaction_date' => '2026-09-08',
            'debit' => 125_000,
            'credit' => 0,
        ]);

        $this->fetcher->fetchAndReplace($reconciliation);

        $gl->delete();

        $staleCount = $this->fetcher->refreshStaleFlags($reconciliation);

        $this->assertSame(1, $staleCount);

        $staleLines = $this->fetcher->staleLines($reconciliation);
        $this->assertCount(1, $staleLines);
        $this->assertSame('The general ledger row no longer exists.', $staleLines->first()['stale_reason']);
    }

    public function test_refresh_stale_flags_detects_debit_change_and_clears_when_restored(): void
    {
        $reconciliation = $this->makeReconciliation();

        $gl = $this->createGlEntry([
            'transaction_date' => '2026-09-11',
            'debit' => 1_000_000,
            'credit' => 0,
        ]);

        $this->fetcher->fetchAndReplace($reconciliation);

        $gl->update(['debit' => 1_250_000]);

        $staleCount = $this->fetcher->refreshStaleFlags($reconciliation);
        $this->assertSame(1, $staleCount);

        $line = BankReconciliationBookLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('general_ledger_id', $gl->id)
            ->firstOrFail();

        $this->assertTrue($line->is_stale);
        $this->assertStringContainsString('1,000,000.00', (string) $line->stale_reason);
        $this->assertStringContainsString('1,250,000.00', (string) $line->stale_reason);

        $gl->update(['debit' => 1_000_000]);

        $cleared = $this->fetcher->refreshStaleFlags($reconciliation);
        $this->assertSame(0, $cleared);

        $line->refresh();
        $this->assertFalse($line->is_stale);
        $this->assertNull($line->stale_reason);
    }

    public function test_outstanding_and_excluded_lines_survive_re_fetch(): void
    {
        $reconciliation = $this->makeReconciliation();

        $this->createGlEntry([
            'transaction_date' => '2026-09-14',
            'debit' => 50_000,
            'credit' => 0,
        ]);

        $outstanding = BankReconciliationBookLine::factory()->outstanding()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-14',
            'general_ledger_id' => null,
        ]);

        $excluded = BankReconciliationBookLine::factory()->excluded()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-15',
            'general_ledger_id' => null,
        ]);

        $this->fetcher->fetchAndReplace($reconciliation);

        $outstanding->refresh();
        $excluded->refresh();

        $this->assertSame(BankBookLineStatus::Outstanding, $outstanding->match_status);
        $this->assertSame(BankBookLineStatus::Excluded, $excluded->match_status);
        $this->assertNotNull(BankReconciliationBookLine::query()->find($outstanding->id));
        $this->assertNotNull(BankReconciliationBookLine::query()->find($excluded->id));
    }

    public function test_fetch_on_locked_reconciliation_throws_and_does_not_change_lines(): void
    {
        $periods = [
            ['periode' => '2026-10-01', 'period_end_date' => '2026-10-31'],
            ['periode' => '2026-11-01', 'period_end_date' => '2026-11-30'],
        ];

        foreach ([BankReconciliationStatus::PendingValidation, BankReconciliationStatus::Completed] as $index => $status) {
            $reconciliation = $this->makeReconciliation(array_merge($periods[$index], ['status' => $status]));

            $this->createGlEntry([
                'transaction_date' => '2026-09-16',
                'debit' => 10_000,
                'credit' => 0,
            ]);

            $linesBefore = BankReconciliationBookLine::query()
                ->where('bank_reconciliation_id', $reconciliation->id)
                ->count();

            try {
                $this->fetcher->fetchAndReplace($reconciliation);
                $this->fail('Expected InvalidArgumentException for status '.$status->value);
            } catch (InvalidArgumentException $exception) {
                $this->assertStringContainsString('locked', strtolower($exception->getMessage()));
            }

            $linesAfter = BankReconciliationBookLine::query()
                ->where('bank_reconciliation_id', $reconciliation->id)
                ->count();

            $this->assertSame($linesBefore, $linesAfter);
        }
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
     * @param  array<string, mixed>  $attributes
     */
    private function createGlEntry(array $attributes = []): GeneralLedger
    {
        return GeneralLedger::query()->create(array_merge([
            'hotel_id' => $this->hotel->id,
            'chart_of_account_id' => $this->bankCoa->id,
            'accounting_period_id' => $this->accountingPeriod->id,
            'transaction_date' => '2026-09-01',
            'debit' => 0,
            'credit' => 0,
            'description' => 'Test GL row',
            'reference_number' => 'GL-TEST',
            'source_type' => 'test',
            'source_id' => 1,
        ], $attributes));
    }
}
