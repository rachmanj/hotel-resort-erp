<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\BankBookLineStatus;
use App\Enums\BankReconciliationStatus;
use App\Enums\BankStatementLineStatus;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationAudit;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedger;
use App\Models\Hotel;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\BankReconciliation\BankBookLineFetcher;
use App\Services\Accounting\BankReconciliation\BankReconciliationBalanceService;
use App\Services\Accounting\BankReconciliation\BankReconciliationMatchingService;
use App\Services\Accounting\BankReconciliation\BankReconciliationWorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BankReconciliationEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const OPENING = 10_000_000.0;

    private const CLOSING = 13_013_500.0;

    private Hotel $hotel;

    private ChartOfAccount $bankCoa;

    private ChartOfAccount $bankChargesCoa;

    private ChartOfAccount $jasaGiroCoa;

    private ChartOfAccount $pphFinalCoa;

    private BankAccount $bankAccount;

    private AccountingPeriod $openPeriod;

    private User $preparer;

    private User $validator;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Queue::fake();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'E2E Bank Rec Hotel',
            'code' => 'E2E',
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

        $this->pphFinalCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '2-2220',
            'name' => 'PPh Final Jasa Giro Terutang',
            'account_type' => 'liability',
            'normal_balance' => 'credit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '1234567890',
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

        AccountingPeriod::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => '2026-10',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'open',
        ]);

        $this->preparer = User::factory()->create(['name' => 'Preparer Finance']);
        $this->preparer->assignRole('finance');
        $this->hotel->users()->attach($this->preparer->id);

        $this->validator = User::factory()->create(['name' => 'Validator Manager']);
        $this->validator->assignRole('manager');
        $this->hotel->users()->attach($this->validator->id);

        $this->seedGeneralLedgerForSeptember();
    }

    public function test_full_bank_reconciliation_lifecycle_from_session_through_validation(): void
    {
        $balanceService = app(BankReconciliationBalanceService::class);
        $matchingService = app(BankReconciliationMatchingService::class);
        $bookFetcher = app(BankBookLineFetcher::class);
        $workflow = app(BankReconciliationWorkflowService::class);

        $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.store'), [
                'bank_account_id' => $this->bankAccount->id,
                'period_end_date' => '2026-09-30',
                'statement_balance' => self::CLOSING,
            ], $this->idempotencyHeaders())
            ->assertRedirect();

        $reconciliation = BankReconciliation::query()
            ->where('bank_account_id', $this->bankAccount->id)
            ->whereDate('period_end_date', '2026-09-30')
            ->firstOrFail();

        $this->assertSame(BankReconciliationStatus::InReview, $reconciliation->status);
        $this->assertSame($this->preparer->id, $reconciliation->created_by);

        $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.import-lines', $reconciliation), [
                'lines' => [
                    [
                        'statement_date' => '2026-09-05',
                        'statement_amount' => 5_000_000,
                        'statement_line_ref' => 'DEP-SEP-001',
                        'description' => 'Guest deposit transfer',
                    ],
                    [
                        'statement_date' => '2026-09-10',
                        'statement_amount' => -2_000_000,
                        'statement_line_ref' => 'PAY-SEP-002',
                        'description' => 'Vendor payment',
                    ],
                    [
                        'statement_date' => '2026-09-12',
                        'statement_amount' => -6_500,
                        'description' => 'BIAYA ADMINISTRASI',
                    ],
                    [
                        'statement_date' => '2026-09-18',
                        'statement_amount' => 25_000,
                        'description' => 'BUNGA JASA GIRO',
                    ],
                    [
                        'statement_date' => '2026-09-18',
                        'statement_amount' => -5_000,
                        'description' => 'PPh Final jasa giro',
                    ],
                ],
            ], $this->idempotencyHeaders())
            ->assertRedirect();

        $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.balances', $reconciliation), [
                'statement_opening_balance' => self::OPENING,
                'statement_closing_balance' => self::CLOSING,
            ], $this->idempotencyHeaders())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $reconciliation->refresh()->load('lines');
        $this->assertSame(5, $reconciliation->lines->count());
        $this->assertNotNull($reconciliation->statement_opening_balance);
        $this->assertNotNull($reconciliation->statement_closing_balance);
        $crossFoot = $balanceService->crossFoot($reconciliation);
        $this->assertTrue(
            $crossFoot === true,
            'Cross-foot failed: '.json_encode([
                'opening' => $reconciliation->statement_opening_balance,
                'closing' => $reconciliation->statement_closing_balance,
                'movement' => $reconciliation->lines->sum(fn ($line) => $line->netAmount()),
                'diagnostic' => $balanceService->diagnostic($reconciliation),
            ]),
        );

        $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.book-lines.refresh', $reconciliation), [], $this->idempotencyHeaders())
            ->assertRedirect();

        $reconciliation->refresh()->load('bookLines');
        $this->assertSame(5, $reconciliation->bookLines->count());
        $this->assertSame(self::OPENING, (float) $reconciliation->book_opening_balance);

        $interestStatement = $this->statementLineByDescription($reconciliation, 'BUNGA JASA GIRO');
        $pphStatement = $this->statementLineByDescription($reconciliation, 'PPh Final jasa giro');
        $interestBook = $this->bookLineByReference($reconciliation, 'INT-SEP');
        $pphBook = $this->bookLineByReference($reconciliation, 'PPH-SEP');

        $interestGroup = $matchingService->manualMatch(
            $reconciliation,
            [$interestStatement->id, $pphStatement->id],
            [$interestBook->id, $pphBook->id],
            $this->preparer,
        );

        $this->assertSame(0.0, (float) $interestGroup->difference);

        $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.auto-match', $reconciliation), [], $this->idempotencyHeaders())
            ->assertRedirect();

        $depositLine = $this->statementLineByRef($reconciliation, 'DEP-SEP-001');
        $paymentLine = $this->statementLineByRef($reconciliation, 'PAY-SEP-002');

        $this->assertNotSame(BankStatementLineStatus::Unmatched, $depositLine->fresh()->match_status);
        $this->assertNotSame(BankStatementLineStatus::Unmatched, $paymentLine->fresh()->match_status);

        $outstandingBook = $this->bookLineByReference($reconciliation, 'CHK-OUT');
        $matchingService->markBookLineOutstanding(
            $reconciliation,
            $outstandingBook,
            'Deposit recorded in books, not yet on the bank statement',
            $this->preparer,
        );

        $sepSnapshot = $reconciliation->fresh(['lines', 'bookLines'])->toArray();

        $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.store'), [
                'bank_account_id' => $this->bankAccount->id,
                'period_end_date' => '2026-10-31',
                'statement_balance' => self::CLOSING,
            ], $this->idempotencyHeaders())
            ->assertRedirect();

        $october = BankReconciliation::query()
            ->where('bank_account_id', $this->bankAccount->id)
            ->whereDate('period_end_date', '2026-10-31')
            ->firstOrFail();

        $carriedBook = BankReconciliationBookLine::query()
            ->where('bank_reconciliation_id', $october->id)
            ->where('is_carried_forward', true)
            ->where('carried_from_book_line_id', $outstandingBook->id)
            ->first();

        $this->assertNotNull($carriedBook);
        $this->assertSame($outstandingBook->id, $carriedBook->carried_from_book_line_id);
        $this->assertSame($reconciliation->id, $carriedBook->origin_reconciliation_id);

        $reconciliation->refresh();
        $this->assertSame($sepSnapshot['status'], $reconciliation->status->value);
        $this->assertSame(
            BankBookLineStatus::Outstanding,
            $outstandingBook->fresh()->match_status,
        );

        $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.import-lines', $reconciliation), [
                'lines' => [
                    [
                        'statement_date' => '2026-09-25',
                        'statement_amount' => 500_000,
                        'statement_line_ref' => 'CHK-OUT',
                        'description' => 'Deposit cleared on bank statement',
                    ],
                ],
            ], $this->idempotencyHeaders())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $matchingService->unmarkBookLineOutstanding($reconciliation, $outstandingBook->fresh(), $this->preparer);
        $clearedStatement = $this->statementLineByRef($reconciliation, 'CHK-OUT');
        $matchingService->manualMatch(
            $reconciliation,
            [$clearedStatement->id],
            [$outstandingBook->fresh()->id],
            $this->preparer,
        );

        $reconciliation->update([
            'statement_closing_balance' => self::CLOSING + 500_000,
            'statement_balance' => self::CLOSING + 500_000,
        ]);

        $bankChargeLine = $this->statementLineByDescription($reconciliation, 'BIAYA ADMINISTRASI');

        try {
            $workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $this->preparer);
            $this->fail('Expected submit to fail while the bank charge remains unmatched.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unmatched', $exception->getMessage());
        }

        $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.adjustments.store', $reconciliation), [
                'statement_line_id' => $bankChargeLine->id,
                'counter_account_id' => $this->bankChargesCoa->id,
                'description' => 'Monthly bank admin fee',
            ], $this->idempotencyHeaders())
            ->assertRedirect();

        $this->assertTrue(
            JournalEntry::query()
                ->whereHas('lines', fn ($q) => $q->where('chart_of_account_id', $this->bankChargesCoa->id))
                ->exists()
        );

        $bankChargeLine->refresh();
        $adjustingJournalId = $bankChargeLine->adjusting_journal_id;
        $this->assertNotNull($adjustingJournalId);

        $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.adjustments.reverse', [
                'bankReconciliation' => $reconciliation,
                'line' => $bankChargeLine,
            ]), [], $this->idempotencyHeaders())
            ->assertRedirect();

        $counterNet = GeneralLedger::query()
            ->whereIn('chart_of_account_id', [$this->bankChargesCoa->id, $this->bankCoa->id])
            ->where('source_type', 'journal_entry')
            ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
            ->value('net');

        $this->assertSame(0.0, (float) $counterNet);
        $this->assertNull($bankChargeLine->fresh()->adjusting_journal_id);

        $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.adjustments.store', $reconciliation), [
                'statement_line_id' => $bankChargeLine->id,
                'counter_account_id' => $this->bankChargesCoa->id,
                'description' => 'Monthly bank admin fee (reposted)',
            ], $this->idempotencyHeaders())
            ->assertRedirect();

        $bookFetcher->refreshStaleFlags($reconciliation->fresh());
        $reconciliation->refresh()->load(['lines', 'bookLines']);

        $this->assertSame(0, $balanceService->unmatchedBankCount($reconciliation));
        $this->assertSame(0, $balanceService->unmatchedBookCount($reconciliation));
        $this->assertTrue(
            $balanceService->isBalanced($reconciliation),
            $balanceService->diagnostic($reconciliation),
        );

        $submitted = $workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $this->preparer);
        $this->assertSame(BankReconciliationStatus::PendingValidation, $submitted->status);
        $this->assertTrue($submitted->isLockedForEditing());

        $completed = $workflow->validateReconciliation($submitted->fresh(), $this->validator);
        $this->assertSame(BankReconciliationStatus::Completed, $completed->status);

        $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('accounting.bank-rec.report', $completed))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/BankReconciliation/Report')
                ->has('balanceProof'));

        $pdf = $this->actingAs($this->preparer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('accounting.bank-rec.report.download', $completed));

        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $actions = BankReconciliationAudit::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->orderBy('id')
            ->pluck('action')
            ->all();

        foreach (['matched', 'outstanding', 'adjusted', 'adjustment_reversed', 'submitted', 'validated'] as $needle) {
            $this->assertContains($needle, $actions);
        }

        $this->assertTrue(
            $actions !== [] && $actions[array_key_first($actions)] === 'matched'
        );
        $this->assertSame('validated', $actions[array_key_last($actions)]);

        $this->assertSame(
            0,
            BankReconciliationLine::query()
                ->where('bank_reconciliation_id', $reconciliation->id)
                ->where('match_status', BankStatementLineStatus::Unmatched)
                ->count()
        );
    }

    /**
     * @return array<string, string>
     */
    private function idempotencyHeaders(): array
    {
        return ['X-Idempotency-Key' => (string) Str::uuid()];
    }

    private function seedGeneralLedgerForSeptember(): void
    {
        $this->createGl([
            'transaction_date' => '2026-08-31',
            'debit' => self::OPENING,
            'credit' => 0,
            'reference_number' => 'OPEN-AUG',
            'description' => 'Opening balance',
        ]);

        $this->createGl([
            'transaction_date' => '2026-09-05',
            'debit' => 5_000_000,
            'credit' => 0,
            'reference_number' => 'DEP-SEP-001',
            'description' => 'Guest deposit transfer',
        ]);

        $this->createGl([
            'transaction_date' => '2026-09-10',
            'debit' => 0,
            'credit' => 2_000_000,
            'reference_number' => 'PAY-SEP-002',
            'description' => 'Vendor payment',
        ]);

        $this->createGl([
            'transaction_date' => '2026-09-18',
            'debit' => 25_000,
            'credit' => 0,
            'reference_number' => 'INT-SEP',
            'description' => 'Interest income',
        ]);

        $this->createGl([
            'transaction_date' => '2026-09-18',
            'debit' => 0,
            'credit' => 5_000,
            'reference_number' => 'PPH-SEP',
            'description' => 'PPh Final withholding',
        ]);

        $this->createGl([
            'transaction_date' => '2026-09-25',
            'debit' => 500_000,
            'credit' => 0,
            'reference_number' => 'CHK-OUT',
            'description' => 'Deposit in transit',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createGl(array $attributes): GeneralLedger
    {
        return GeneralLedger::query()->create(array_merge([
            'hotel_id' => $this->hotel->id,
            'chart_of_account_id' => $this->bankCoa->id,
            'accounting_period_id' => $this->openPeriod->id,
            'debit' => 0,
            'credit' => 0,
            'description' => 'E2E GL',
            'reference_number' => 'GL-E2E',
            'source_type' => 'test',
            'source_id' => 1,
        ], $attributes));
    }

    private function statementLineByRef(BankReconciliation $reconciliation, string $ref): BankReconciliationLine
    {
        return BankReconciliationLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('reference', $ref)
            ->firstOrFail();
    }

    private function statementLineByDescription(BankReconciliation $reconciliation, string $needle): BankReconciliationLine
    {
        return BankReconciliationLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('description', 'like', '%'.$needle.'%')
            ->firstOrFail();
    }

    private function bookLineByReference(BankReconciliation $reconciliation, string $ref): BankReconciliationBookLine
    {
        return BankReconciliationBookLine::query()
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->where('reference_number', $ref)
            ->firstOrFail();
    }
}
