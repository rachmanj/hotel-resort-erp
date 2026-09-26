<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\BankReconciliationStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationMatch;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankReconciliationHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_exits_zero_when_clean(): void
    {
        $this->artisan('bankrec:health')
            ->assertSuccessful()
            ->expectsOutputToContain('Bank reconciliation health: clean');
    }

    public function test_health_reports_orphan_match_group(): void
    {
        $session = $this->makeSession();

        BankReconciliationMatch::factory()->create([
            'bank_reconciliation_id' => $session->id,
        ]);

        $this->artisan('bankrec:health')
            ->assertFailed()
            ->expectsOutputToContain('Orphan match groups');
    }

    public function test_health_reports_stale_book_lines(): void
    {
        $session = $this->makeSession();

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $session->id,
            'is_stale' => true,
            'stale_reason' => 'Ledger debit changed after snapshot.',
        ]);

        $this->artisan('bankrec:health')
            ->assertFailed()
            ->expectsOutputToContain('stale book line');
    }

    private function makeSession(): BankReconciliation
    {
        $hotel = Hotel::query()->create([
            'name' => 'Health Hotel',
            'code' => 'HLH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $hotel->id,
            'account_code' => '1-1322',
            'name' => 'Health Bank',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::query()->create([
            'hotel_id' => $hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '3334445555',
            'account_name' => 'Health',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        return BankReconciliation::factory()->for($bankAccount)->create([
            'status' => BankReconciliationStatus::InReview,
            'period_end_date' => '2026-09-30',
            'periode' => '2026-09-01',
        ]);
    }
}
