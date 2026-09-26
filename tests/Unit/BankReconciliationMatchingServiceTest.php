<?php

namespace Tests\Unit;

use App\Enums\BankAccountType;
use App\Enums\BankBookLineStatus;
use App\Enums\BankMatchType;
use App\Enums\BankStatementLineStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\BankReconciliationMatch;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Services\Accounting\BankReconciliation\BankReconciliationBalanceService;
use App\Services\Accounting\BankReconciliation\BankReconciliationMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BankReconciliationMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BankReconciliationMatchingService $service;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BankReconciliationMatchingService(new BankReconciliationBalanceService);

        $hotel = Hotel::query()->create([
            'name' => 'Matching Test Hotel',
            'code' => 'MTH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $hotel->id]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $hotel->id,
            'account_code' => '1-1299',
            'name' => 'Test Bank Matching',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '9998887777',
            'account_name' => 'Matching Account',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);
    }

    public function test_exact_match_same_date_creates_zero_difference_group(): void
    {
        $date = '2026-09-15';
        $reconciliation = $this->makeReconciliation();

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 1_000_000,
        ]);

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 1_000_000,
            'credit' => 0,
        ]);

        $created = $this->service->autoMatch($reconciliation);

        $this->assertSame(1, $created);

        $match = BankReconciliationMatch::query()->first();
        $this->assertNotNull($match);
        $this->assertSame(BankMatchType::AutoExact, $match->match_type);
        $this->assertSame(0.0, (float) $match->difference);

        $statement = BankReconciliationLine::query()->first();
        $book = BankReconciliationBookLine::query()->first();
        $this->assertSame(BankStatementLineStatus::Matched, $statement->match_status);
        $this->assertSame(BankBookLineStatus::Matched, $book->match_status);
    }

    public function test_date_window_one_day_matches_six_days_apart_does_not(): void
    {
        $reconciliation = $this->makeReconciliation();

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-10',
            'description' => 'UNRELATED ALPHA',
            'debit' => 0,
            'credit' => 500_000,
        ]);

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-11',
            'description' => 'COMPLETELY DIFFERENT BETA',
            'debit' => 500_000,
            'credit' => 0,
        ]);

        $this->assertSame(1, $this->service->autoMatch($reconciliation));

        $reconciliation2 = $this->makeReconciliation();

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation2->id,
            'posting_date' => '2026-09-01',
            'description' => 'UNRELATED ALPHA',
            'debit' => 0,
            'credit' => 500_000,
        ]);

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation2->id,
            'posting_date' => '2026-09-07',
            'description' => 'COMPLETELY DIFFERENT BETA',
            'debit' => 500_000,
            'credit' => 0,
        ]);

        $this->assertSame(0, $this->service->autoMatch($reconciliation2));
        $this->assertSame(0, BankReconciliationMatch::query()->where('bank_reconciliation_id', $reconciliation2->id)->count());
    }

    public function test_reference_match_with_normalized_references(): void
    {
        $reconciliation = $this->makeReconciliation();

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-10',
            'reference' => 'TRF/2026-09 #12',
            'description' => 'Transfer',
            'debit' => 0,
            'credit' => 750_000,
        ]);

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-13',
            'reference_number' => 'TRF202609 12',
            'description' => 'Other label',
            'debit' => 750_000,
            'credit' => 0,
        ]);

        $this->service->autoMatch($reconciliation);

        $match = BankReconciliationMatch::query()->first();
        $this->assertNotNull($match);
        $this->assertSame(BankMatchType::AutoReference, $match->match_type);
    }

    public function test_fuzzy_match_with_similar_descriptions(): void
    {
        $reconciliation = $this->makeReconciliation();

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-05',
            'reference' => 'CASH-001',
            'description' => 'SETORAN TUNAI TAMU ANDI',
            'debit' => 0,
            'credit' => 2_000_000,
        ]);

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-09',
            'reference_number' => 'JV-OTHER',
            'description' => 'Setoran tunai Andi - kamar 204',
            'debit' => 2_000_000,
            'credit' => 0,
        ]);

        $this->service->autoMatch($reconciliation);

        $match = BankReconciliationMatch::query()->first();
        $this->assertNotNull($match);
        $this->assertSame(BankMatchType::AutoFuzzy, $match->match_type);
        $this->assertGreaterThan(0.5, (float) $match->confidence_score);
    }

    public function test_split_one_statement_to_three_book_lines(): void
    {
        $reconciliation = $this->makeReconciliation();
        $date = '2026-09-12';

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 3_000_000,
        ]);

        foreach ([1, 2, 3] as $offset) {
            BankReconciliationBookLine::factory()->create([
                'bank_reconciliation_id' => $reconciliation->id,
                'posting_date' => $date,
                'debit' => 1_000_000,
                'credit' => 0,
                'description' => "Part {$offset}",
            ]);
        }

        $this->service->autoMatch($reconciliation);

        $match = BankReconciliationMatch::query()->first();
        $this->assertNotNull($match);
        $this->assertSame(BankMatchType::AutoSplit, $match->match_type);
        $this->assertSame(4, $match->matchLines()->count() + $match->matchLedgerRows()->count());
    }

    public function test_split_two_statements_to_one_book_line(): void
    {
        $reconciliation = $this->makeReconciliation();
        $date = '2026-09-14';

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 1_200_000,
        ]);

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 800_000,
        ]);

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 2_000_000,
            'credit' => 0,
        ]);

        $this->service->autoMatch($reconciliation);

        $match = BankReconciliationMatch::query()->first();
        $this->assertNotNull($match);
        $this->assertSame(BankMatchType::AutoSplit, $match->match_type);
        $this->assertSame(2, $match->matchLines()->count());
        $this->assertSame(1, $match->matchLedgerRows()->count());
    }

    public function test_same_polarity_is_not_auto_matched_and_manual_match_throws(): void
    {
        $reconciliation = $this->makeReconciliation();
        $date = '2026-09-16';

        $statement = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 100,
            'credit' => 0,
        ]);

        $book = BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 100,
            'credit' => 0,
        ]);

        $this->assertSame(0, $this->service->autoMatch($reconciliation));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/100/');

        $this->service->manualMatch($reconciliation, [$statement->id], [$book->id]);
    }

    public function test_auto_match_is_idempotent_and_manual_groups_survive_clear_auto(): void
    {
        $reconciliation = $this->makeReconciliation();
        $date = '2026-09-18';

        $autoStatement = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 100_000,
        ]);

        $autoBook = BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 100_000,
            'credit' => 0,
        ]);

        $manualStatement = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 50_000,
        ]);

        $manualBook = BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 50_000,
            'credit' => 0,
        ]);

        $this->service->manualMatch($reconciliation, [$manualStatement->id], [$manualBook->id]);

        $this->assertSame(1, $this->service->autoMatch($reconciliation));
        $this->assertSame(2, BankReconciliationMatch::query()->where('bank_reconciliation_id', $reconciliation->id)->count());

        $this->assertSame(1, $this->service->autoMatch($reconciliation));
        $this->assertSame(2, BankReconciliationMatch::query()->where('bank_reconciliation_id', $reconciliation->id)->count());

        $this->service->clearAutoMatches($reconciliation);

        $this->assertSame(1, BankReconciliationMatch::query()->where('bank_reconciliation_id', $reconciliation->id)->count());
        $this->assertSame(BankMatchType::Manual, BankReconciliationMatch::query()->first()->match_type);

        $autoStatement->refresh();
        $autoBook->refresh();
        $this->assertSame(BankStatementLineStatus::Unmatched, $autoStatement->match_status);
        $this->assertSame(BankBookLineStatus::Unmatched, $autoBook->match_status);
    }

    public function test_unmatch_group_restores_lines_and_removes_pivots(): void
    {
        $reconciliation = $this->makeReconciliation();
        $date = '2026-09-20';

        $statement = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 300_000,
        ]);

        $book = BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 300_000,
            'credit' => 0,
        ]);

        $match = $this->service->manualMatch($reconciliation, [$statement->id], [$book->id]);

        $this->service->unmatchGroup($reconciliation, $match);

        $this->assertNull(BankReconciliationMatch::query()->find($match->id));
        $statement->refresh();
        $book->refresh();
        $this->assertSame(BankStatementLineStatus::Unmatched, $statement->match_status);
        $this->assertSame(BankBookLineStatus::Unmatched, $book->match_status);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeReconciliation(array $attributes = []): BankReconciliation
    {
        static $periodOffset = 0;
        $periodEnd = now()->startOfMonth()->addMonths($periodOffset)->endOfMonth();
        $periodOffset++;

        return BankReconciliation::factory()
            ->for($this->bankAccount)
            ->create(array_merge([
                'period_end_date' => $periodEnd,
                'periode' => $periodEnd->toDateString(),
            ], $attributes));
    }
}
