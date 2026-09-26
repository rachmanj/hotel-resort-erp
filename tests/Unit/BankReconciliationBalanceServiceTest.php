<?php

namespace Tests\Unit;

use App\Enums\BankAccountType;
use App\Enums\BankBookLineStatus;
use App\Enums\BankStatementLineStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Services\Accounting\BankReconciliation\BankReconciliationBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankReconciliationBalanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private BankReconciliationBalanceService $service;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BankReconciliationBalanceService;

        $hotel = Hotel::query()->create([
            'name' => 'Balance Test Hotel',
            'code' => 'BTH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $hotel->id]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $hotel->id,
            'account_code' => '1-1298',
            'name' => 'Test Bank',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '1112223333',
            'account_name' => 'Balance Account',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);
    }

    public function test_opposite_polarity_clears_to_zero_and_can_be_balanced(): void
    {
        $reconciliation = $this->makeReconciliation([
            'statement_opening_balance' => 0,
            'statement_closing_balance' => 1_000_000,
            'book_closing_balance' => 1_000_000,
        ]);

        BankReconciliationLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 0,
            'credit' => 1_000_000,
        ]);

        BankReconciliationBookLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 1_000_000,
            'credit' => 0,
        ]);

        $reconciliation->load(['lines', 'bookLines']);

        $this->assertSame(0.0, $this->service->difference($reconciliation));
        $this->assertTrue($this->service->crossFoot($reconciliation));
        $this->assertTrue($this->service->isBalanced($reconciliation));
    }

    public function test_same_side_polarity_is_not_balanced(): void
    {
        $reconciliation = $this->makeReconciliation();

        BankReconciliationLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 100,
            'credit' => 0,
        ]);

        BankReconciliationBookLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 100,
            'credit' => 0,
        ]);

        $reconciliation->load(['lines', 'bookLines']);

        $this->assertSame(200.0, $this->service->difference($reconciliation));
        $this->assertFalse($this->service->isBalanced($reconciliation));
    }

    public function test_excluded_and_outstanding_lines_are_excluded_from_cleared_nets(): void
    {
        $reconciliation = $this->makeReconciliation();

        BankReconciliationLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 0,
            'credit' => 500,
        ]);

        BankReconciliationBookLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 500,
            'credit' => 0,
        ]);

        $reconciliation->load(['lines', 'bookLines']);

        $baselineBank = $this->service->bankNet($reconciliation);
        $baselineBook = $this->service->bookNet($reconciliation);

        BankReconciliationLine::factory()->excluded()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 0,
            'credit' => 9_999,
        ]);

        BankReconciliationLine::factory()->outstanding()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 0,
            'credit' => 8_888,
        ]);

        BankReconciliationBookLine::factory()->excluded()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 7_777,
            'credit' => 0,
        ]);

        BankReconciliationBookLine::factory()->outstanding()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 6_666,
            'credit' => 0,
        ]);

        $reconciliation->load(['lines', 'bookLines']);

        $this->assertSame($baselineBank, $this->service->bankNet($reconciliation));
        $this->assertSame($baselineBook, $this->service->bookNet($reconciliation));
    }

    public function test_cross_foot_detects_wrong_statement_total_and_requires_balances(): void
    {
        $withoutBalances = $this->makeReconciliation();
        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $withoutBalances->id,
            'debit' => 0,
            'credit' => 1_000_000,
        ]);
        $withoutBalances->load('lines');
        $this->assertNull($this->service->crossFoot($withoutBalances));

        $wrongTotal = $this->makeReconciliation([
            'statement_opening_balance' => 0,
            'statement_closing_balance' => 1_000_000,
        ]);
        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $wrongTotal->id,
            'debit' => 0,
            'credit' => 999_000,
        ]);
        $wrongTotal->load('lines');
        $this->assertFalse($this->service->crossFoot($wrongTotal));

        $correct = $this->makeReconciliation([
            'statement_opening_balance' => 0,
            'statement_closing_balance' => 1_000_000,
        ]);
        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $correct->id,
            'debit' => 0,
            'credit' => 1_000_000,
        ]);
        $correct->load('lines');
        $this->assertTrue($this->service->crossFoot($correct));
    }

    public function test_unexplained_difference_follows_reference_identity(): void
    {
        $reconciliation = $this->makeReconciliation([
            'statement_closing_balance' => 1_000_000,
            'book_closing_balance' => 1_050_000,
        ]);

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 50_000,
            'credit' => 0,
            'match_status' => BankBookLineStatus::Unmatched,
        ]);

        $reconciliation->load(['lines', 'bookLines']);

        $this->assertSame(0.0, $this->service->unexplainedDifference($reconciliation));

        $reconciliation->update(['book_closing_balance' => 1_060_000]);
        $reconciliation->refresh();

        $this->assertSame(-10_000.0, $this->service->unexplainedDifference($reconciliation));
    }

    public function test_incomplete_and_diagnostic_messages(): void
    {
        $missingBalances = $this->makeReconciliation();
        $this->assertTrue($this->service->incomplete($missingBalances));
        $this->assertStringContainsString(
            'Closing balances are required on both the statement and the book side',
            $this->service->diagnostic($missingBalances),
        );

        $unreconciled = $this->makeReconciliation([
            'statement_opening_balance' => 0,
            'statement_closing_balance' => 1_000_000,
            'book_closing_balance' => 1_000_000,
        ]);

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $unreconciled->id,
            'match_status' => BankStatementLineStatus::Unmatched,
            'debit' => 0,
            'credit' => 50_000,
        ]);

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $unreconciled->id,
            'match_status' => BankBookLineStatus::Unmatched,
            'debit' => 25_000,
            'credit' => 0,
        ]);

        $unreconciled->load(['lines', 'bookLines']);

        $this->assertFalse($this->service->incomplete($unreconciled));
        $diagnostic = $this->service->diagnostic($unreconciled);
        $this->assertStringContainsString('Unmatched lines: 1 statement / 1 book', $diagnostic);
        $this->assertStringContainsString('Unexplained difference:', $diagnostic);
    }

    public function test_status_payload_exposes_documented_keys_with_expected_types(): void
    {
        $reconciliation = $this->makeReconciliation([
            'statement_opening_balance' => 0,
            'statement_closing_balance' => 1_000_000,
            'book_closing_balance' => 1_000_000,
        ]);

        BankReconciliationLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 0,
            'credit' => 1_000_000,
        ]);

        BankReconciliationBookLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 1_000_000,
            'credit' => 0,
        ]);

        $reconciliation->load(['lines', 'bookLines']);

        $payload = $this->service->statusPayload($reconciliation);

        $expectedKeys = [
            'bank_net',
            'book_net',
            'difference',
            'unmatched_bank_count',
            'unmatched_book_count',
            'deposits_in_transit',
            'outstanding_checks',
            'outstanding_bank_net',
            'statement_opening',
            'statement_closing',
            'book_closing',
            'adjusted_statement_balance',
            'reconciliation_difference',
            'unexplained_difference',
            'cross_foot',
            'is_balanced',
            'incomplete',
            'diagnostic',
        ];

        $this->assertSame($expectedKeys, array_keys($payload));

        foreach ([
            'bank_net',
            'book_net',
            'difference',
            'deposits_in_transit',
            'outstanding_checks',
            'outstanding_bank_net',
            'adjusted_statement_balance',
            'reconciliation_difference',
            'unexplained_difference',
        ] as $moneyKey) {
            $this->assertIsFloat($payload[$moneyKey]);
        }

        $this->assertIsInt($payload['unmatched_bank_count']);
        $this->assertIsInt($payload['unmatched_book_count']);
        $this->assertIsBool($payload['is_balanced']);
        $this->assertIsBool($payload['incomplete']);
        $this->assertIsString($payload['diagnostic']);
        $this->assertTrue($payload['cross_foot'] === null || is_bool($payload['cross_foot']));
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
