<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\BankReconciliationStatus;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\BankReconciliation\BankReconciliationWorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AccountingPeriodCloseGuardTest extends TestCase
{
    use RefreshDatabase;

    private AccountingPeriodService $periodService;

    private BankReconciliationWorkflowService $workflow;

    private Hotel $hotel;

    private BankAccount $bankAccount;

    private AccountingPeriod $septemberPeriod;

    private User $closer;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->periodService = app(AccountingPeriodService::class);
        $this->workflow = app(BankReconciliationWorkflowService::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Period Guard Hotel',
            'code' => 'PGH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $this->hotel->id]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '1-1320',
            'name' => 'Guard Bank GL',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '1122334455',
            'account_name' => 'Guard Account',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $this->septemberPeriod = AccountingPeriod::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => '2026-09',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'open',
        ]);

        $this->closer = User::factory()->create();
        $this->closer->assignRole('finance');
        $this->hotel->users()->attach($this->closer->id);
    }

    public function test_closing_period_with_unfinished_reconciliation_is_refused(): void
    {
        $reconciliation = $this->makeSeptemberReconciliation(['status' => BankReconciliationStatus::InReview]);

        try {
            $this->periodService->closePeriod($this->septemberPeriod->fresh(), $this->closer);
            $this->fail('Expected InvalidArgumentException when bank reconciliation blocks close.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Cannot close 2026-09', $exception->getMessage());
            $this->assertStringContainsString('BCA', $exception->getMessage());
            $this->assertStringContainsString('2026-09', $exception->getMessage());
        }

        $this->septemberPeriod->refresh();
        $this->assertTrue($this->septemberPeriod->isOpen());

        $this->assertSame(BankReconciliationStatus::InReview, $reconciliation->fresh()->status);
    }

    public function test_closing_period_succeeds_after_reconciliation_is_completed_or_void(): void
    {
        Notification::fake();

        $preparer = User::factory()->create();
        $preparer->assignRole('front_office');
        $this->hotel->users()->attach($preparer->id);

        $validator = User::factory()->create();
        $validator->assignRole('finance');
        $this->hotel->users()->attach($validator->id);

        $reconciliation = $this->makeBalancedSeptemberReconciliation($preparer);
        $submitted = $this->workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $preparer);
        $this->workflow->validateReconciliation($submitted->fresh(), $validator);

        $closed = $this->periodService->closePeriod($this->septemberPeriod->fresh(), $this->closer);

        $this->assertFalse($closed->isOpen());
    }

    public function test_closing_period_succeeds_when_only_void_reconciliation_exists(): void
    {
        $this->makeSeptemberReconciliation(['status' => BankReconciliationStatus::Void]);

        $closed = $this->periodService->closePeriod($this->septemberPeriod->fresh(), $this->closer);

        $this->assertFalse($closed->isOpen());
    }

    public function test_reconciliation_in_different_month_does_not_block_close(): void
    {
        BankReconciliation::factory()
            ->for($this->bankAccount)
            ->create([
                'period_end_date' => '2026-10-31',
                'periode' => '2026-10-01',
                'status' => BankReconciliationStatus::InReview,
            ]);

        $closed = $this->periodService->closePeriod($this->septemberPeriod->fresh(), $this->closer);

        $this->assertFalse($closed->isOpen());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSeptemberReconciliation(array $overrides = []): BankReconciliation
    {
        return BankReconciliation::factory()
            ->for($this->bankAccount)
            ->create(array_merge([
                'period_end_date' => '2026-09-30',
                'periode' => '2026-09-01',
            ], $overrides));
    }

    private function makeBalancedSeptemberReconciliation(User $preparer): BankReconciliation
    {
        $reconciliation = $this->makeSeptemberReconciliation([
            'statement_opening_balance' => 0,
            'statement_closing_balance' => 1_000_000,
            'book_closing_balance' => 1_000_000,
            'status' => BankReconciliationStatus::InReview,
            'created_by' => $preparer->id,
        ]);

        BankReconciliationLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-15',
            'debit' => 0,
            'credit' => 1_000_000,
        ]);

        BankReconciliationBookLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'posting_date' => '2026-09-15',
            'debit' => 1_000_000,
            'credit' => 0,
        ]);

        return $reconciliation;
    }
}
