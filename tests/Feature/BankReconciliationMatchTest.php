<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\BankBookLineStatus;
use App\Enums\BankReconciliationStatus;
use App\Enums\BankStatementLineStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationAudit;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\BankReconciliationMatch;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Accounting\BankReconciliation\BankReconciliationBalanceService;
use App\Services\Accounting\BankReconciliation\BankReconciliationMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BankReconciliationMatchTest extends TestCase
{
    use RefreshDatabase;

    private BankReconciliationMatchingService $service;

    private BankAccount $bankAccount;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new BankReconciliationMatchingService(new BankReconciliationBalanceService);

        $hotel = Hotel::query()->create([
            'name' => 'Match Feature Hotel',
            'code' => 'MFH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $hotel->id]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $hotel->id,
            'account_code' => '1-1300',
            'name' => 'Feature Bank',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '5556667777',
            'account_name' => 'Feature Account',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create(['hotel_id' => $hotel->id]);
    }

    public function test_manual_match_one_statement_to_three_books_creates_audit(): void
    {
        $reconciliation = $this->makeReconciliation();
        $date = '2026-09-22';

        $statement = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 3_000_000,
        ]);

        $books = [];
        foreach ([1, 2, 3] as $i) {
            $books[] = BankReconciliationBookLine::factory()->create([
                'bank_reconciliation_id' => $reconciliation->id,
                'posting_date' => $date,
                'debit' => 1_000_000,
                'credit' => 0,
                'description' => "Slice {$i}",
            ]);
        }

        $match = $this->service->manualMatch(
            $reconciliation,
            [$statement->id],
            array_map(fn (BankReconciliationBookLine $line): int => $line->id, $books),
            $this->user,
        );

        $this->assertSame(0.0, (float) $match->difference);
        $this->assertSame(1, $match->matchLines()->count());
        $this->assertSame(3, $match->matchLedgerRows()->count());

        $statement->refresh();
        foreach ($books as $book) {
            $book->refresh();
            $this->assertSame(BankBookLineStatus::Manual, $book->match_status);
        }
        $this->assertSame(BankStatementLineStatus::Manual, $statement->match_status);

        $audit = BankReconciliationAudit::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('action', 'matched')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($this->user->id, $audit->performed_by);
    }

    public function test_cannot_match_line_already_in_group_or_excluded(): void
    {
        $reconciliation = $this->makeReconciliation();
        $date = '2026-09-23';

        $statementA = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 100_000,
        ]);

        $statementB = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 100_000,
        ]);

        $book = BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 100_000,
            'credit' => 0,
        ]);

        $this->service->manualMatch($reconciliation, [$statementA->id], [$book->id]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->manualMatch($reconciliation, [$statementB->id], [$book->id]);

        $excluded = BankReconciliationLine::factory()->excluded()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 50_000,
        ]);

        $book2 = BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 50_000,
            'credit' => 0,
        ]);

        try {
            $this->service->manualMatch($reconciliation, [$excluded->id], [$book2->id]);
            $this->fail('Expected InvalidArgumentException for excluded line.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString((string) $excluded->id, $exception->getMessage());
        }
    }

    public function test_manual_match_refused_when_reconciliation_is_pending_validation(): void
    {
        $reconciliation = $this->makeReconciliation(['status' => BankReconciliationStatus::PendingValidation]);
        $date = '2026-09-24';

        $statement = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 10_000,
        ]);

        $book = BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 10_000,
            'credit' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This bank reconciliation is locked for editing');

        $this->service->manualMatch($reconciliation, [$statement->id], [$book->id]);
    }

    public function test_auto_match_refused_when_reconciliation_is_completed(): void
    {
        $reconciliation = $this->makeReconciliation(['status' => BankReconciliationStatus::Completed]);
        $date = '2026-09-24';

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 0,
            'credit' => 10_000,
        ]);

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'debit' => 10_000,
            'credit' => 0,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('This bank reconciliation is locked for editing');

        $this->service->autoMatch($reconciliation);
    }

    public function test_exclude_and_include_statement_line_with_audit(): void
    {
        $reconciliation = $this->makeReconciliation();
        $line = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->excludeStatementLine($reconciliation, $line, '   ', $this->user);

        $this->service->excludeStatementLine($reconciliation, $line, 'Bank fee not in GL', $this->user);

        $line->refresh();
        $this->assertSame(BankStatementLineStatus::Excluded, $line->match_status);
        $this->assertSame('Bank fee not in GL', $line->exclude_reason);

        $this->assertTrue(
            BankReconciliationAudit::query()
                ->where('bank_reconciliation_id', $reconciliation->id)
                ->where('action', 'excluded')
                ->exists()
        );

        $this->service->includeStatementLine($reconciliation, $line, $this->user);

        $line->refresh();
        $this->assertSame(BankStatementLineStatus::Unmatched, $line->match_status);
        $this->assertNull($line->exclude_reason);
    }

    public function test_suggest_matches_is_read_only(): void
    {
        $reconciliation = $this->makeReconciliation();
        $date = '2026-09-25';

        $statement = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'description' => 'SETORAN TUNAI TAMU ANDI',
            'debit' => 0,
            'credit' => 1_000_000,
        ]);

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => $date,
            'description' => 'Setoran tunai Andi',
            'debit' => 1_000_000,
            'credit' => 0,
        ]);

        $matchCountBefore = BankReconciliationMatch::query()->count();
        $auditCountBefore = BankReconciliationAudit::query()->count();
        $statusBefore = $statement->match_status;

        $suggestions = $this->service->suggestMatches($reconciliation, $statement, 5);

        $this->assertNotEmpty($suggestions);
        $this->assertArrayHasKey('score', $suggestions[0]);
        $this->assertArrayHasKey('reason', $suggestions[0]);

        $this->assertSame($matchCountBefore, BankReconciliationMatch::query()->count());
        $this->assertSame($auditCountBefore, BankReconciliationAudit::query()->count());
        $statement->refresh();
        $this->assertSame($statusBefore, $statement->match_status);
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
