<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\FundTransfer;
use App\Models\GeneralLedger;
use App\Models\Hotel;
use App\Models\PettyCashTransaction;
use App\Models\User;
use App\Services\Accounting\GlPostingService;
use Database\Seeders\AccountingDemoSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PettyCashAndFundTransferTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $admin;

    private BankAccount $bankAccount;

    private BankAccount $pettyCashAccount;

    private ChartOfAccount $kasAccount;

    private ChartOfAccount $bankCoa;

    private ChartOfAccount $expenseAccount;

    private ChartOfAccount $dueToTravyDoor;

    private Department $kitchen;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepartmentSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Test Hotel',
            'code' => 'TST',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $this->hotel->id]);

        (new ChartOfAccountsSeeder)->forHotel($this->hotel);
        (new ChartOfAccountsSeeder)->syncIntercompanyAccounts($this->hotel);
        (new AccountingDemoSeeder)->run();

        $this->kasAccount = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $this->hotel->id)
            ->where('account_code', '1-1100')
            ->firstOrFail();

        $this->bankCoa = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $this->hotel->id)
            ->where('account_code', '1-1200')
            ->firstOrFail();

        $this->expenseAccount = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $this->hotel->id)
            ->where('account_code', '6-8100')
            ->firstOrFail();

        $this->dueToTravyDoor = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $this->hotel->id)
            ->where('account_code', '2-2210')
            ->firstOrFail();

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '1234567890',
            'account_name' => 'Operating Bank',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $this->bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $this->pettyCashAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'Petty Cash',
            'account_no' => 'PC-001',
            'account_name' => 'Front Office PC',
            'type' => BankAccountType::PettyCash->value,
            'chart_of_account_id' => $this->kasAccount->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $this->kitchen = Department::query()
            ->withoutGlobalScope('hotel')
            ->whereNull('hotel_id')
            ->where('code', 'kitchen')
            ->firstOrFail();

        $this->admin = User::factory()->create(['hotel_id' => null]);
        $this->admin->assignRole('admin');
        $this->hotel->users()->attach($this->admin->id);
    }

    public function test_petty_cash_cash_out_posts_balanced_gl_with_department(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post('/accounting/petty-cash/transactions', [
                'bank_account_id' => $this->pettyCashAccount->id,
                'direction' => 'out',
                'amount' => 150000,
                'transaction_date' => now()->toDateString(),
                'department_id' => $this->kitchen->id,
                'description' => 'Office supplies purchase',
                'chart_of_account_id' => $this->expenseAccount->id,
            ]);

        $response->assertRedirect();

        $transaction = PettyCashTransaction::query()->firstOrFail();

        $glRows = GeneralLedger::query()
            ->withoutGlobalScope('hotel')
            ->where('source_type', 'petty_cash_transaction')
            ->where('source_id', $transaction->id)
            ->get();

        $this->assertCount(2, $glRows);
        $this->assertSame(150000.0, (float) $glRows->sum('debit'));
        $this->assertSame(150000.0, (float) $glRows->sum('credit'));

        $expenseLine = $glRows->firstWhere('chart_of_account_id', $this->expenseAccount->id);
        $this->assertNotNull($expenseLine);
        $this->assertSame($this->kitchen->id, $expenseLine->department_id);
        $this->assertSame(150000.0, (float) $expenseLine->debit);

        $pcLine = $glRows->firstWhere('chart_of_account_id', $this->kasAccount->id);
        $this->assertNotNull($pcLine);
        $this->assertSame(150000.0, (float) $pcLine->credit);
    }

    public function test_replenish_transfers_bank_to_petty_cash(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post('/accounting/petty-cash/replenish', [
                'from_bank_account_id' => $this->bankAccount->id,
                'to_bank_account_id' => $this->pettyCashAccount->id,
                'amount' => 5000000,
                'transfer_date' => now()->toDateString(),
                'description' => 'Monthly petty cash replenishment',
            ]);

        $response->assertRedirect();

        $transfer = FundTransfer::query()->firstOrFail();
        $this->assertSame($this->bankAccount->id, $transfer->from_bank_account_id);
        $this->assertSame($this->pettyCashAccount->id, $transfer->to_bank_account_id);

        $glRows = GeneralLedger::query()
            ->withoutGlobalScope('hotel')
            ->where('source_type', 'fund_transfer')
            ->where('source_id', $transfer->id)
            ->get();

        $this->assertCount(2, $glRows);
        $this->assertSame(5000000.0, (float) $glRows->sum('debit'));
        $this->assertSame(5000000.0, (float) $glRows->sum('credit'));

        $debitLine = $glRows->firstWhere('chart_of_account_id', $this->kasAccount->id);
        $creditLine = $glRows->firstWhere('chart_of_account_id', $this->bankCoa->id);

        $this->assertNotNull($debitLine);
        $this->assertNotNull($creditLine);
        $this->assertSame(5000000.0, (float) $debitLine->debit);
        $this->assertSame(5000000.0, (float) $creditLine->credit);

        $balance = app(GlPostingService::class)->getBalance($this->kasAccount, null, $this->hotel->id);
        $this->assertSame(5000000.0, $balance);
    }

    public function test_fund_transfer_to_intercompany_account(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post('/accounting/transfers', [
                'from_chart_of_account_id' => $this->bankCoa->id,
                'to_chart_of_account_id' => $this->dueToTravyDoor->id,
                'amount' => 25000000,
                'transfer_date' => now()->toDateString(),
                'description' => 'PAA-PBR intercompany settlement',
            ]);

        $response->assertRedirect();

        $transfer = FundTransfer::query()->firstOrFail();

        $glRows = GeneralLedger::query()
            ->withoutGlobalScope('hotel')
            ->where('source_type', 'fund_transfer')
            ->where('source_id', $transfer->id)
            ->get();

        $this->assertCount(2, $glRows);
        $this->assertSame(25000000.0, (float) $glRows->sum('debit'));
        $this->assertSame(25000000.0, (float) $glRows->sum('credit'));

        $this->assertNotNull($glRows->firstWhere('chart_of_account_id', $this->dueToTravyDoor->id));
        $this->assertNotNull($glRows->firstWhere('chart_of_account_id', $this->bankCoa->id));
    }

    public function test_petty_cash_index_page_loads(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/accounting/petty-cash')
            ->assertOk();
    }

    public function test_transfers_index_page_loads(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/accounting/transfers')
            ->assertOk();
    }

    public function test_intercompany_accounts_seeded_with_alternate_codes(): void
    {
        $dueFrom = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $this->hotel->id)
            ->where('account_code', '1-1450')
            ->first();

        $dueTo = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $this->hotel->id)
            ->where('account_code', '2-2210')
            ->first();

        $this->assertNotNull($dueFrom);
        $this->assertSame('Due from TravyDoor Tour', $dueFrom->name);
        $this->assertNotNull($dueTo);
        $this->assertSame('Due to TravyDoor Tour', $dueTo->name);
    }
}
