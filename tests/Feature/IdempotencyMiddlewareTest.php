<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\Hotel;
use App\Models\IdempotencyKey;
use App\Models\PettyCashTransaction;
use App\Models\User;
use Database\Seeders\AccountingDemoSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IdempotencyMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $admin;

    private BankAccount $pettyCashAccount;

    private ChartOfAccount $expenseAccount;

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

        $kasAccount = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $this->hotel->id)
            ->where('account_code', '1-1100')
            ->firstOrFail();

        $this->expenseAccount = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $this->hotel->id)
            ->where('account_code', '6-8100')
            ->firstOrFail();

        $this->pettyCashAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'Petty Cash',
            'account_no' => 'PC-001',
            'account_name' => 'Front Office PC',
            'type' => BankAccountType::PettyCash->value,
            'chart_of_account_id' => $kasAccount->id,
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

    public function test_duplicate_idempotency_key_creates_only_one_record(): void
    {
        $idempotencyKey = '550e8400-e29b-41d4-a716-446655440000';

        $payload = [
            'bank_account_id' => $this->pettyCashAccount->id,
            'direction' => 'out',
            'amount' => 150000,
            'transaction_date' => now()->toDateString(),
            'department_id' => $this->kitchen->id,
            'description' => 'Office supplies purchase',
            'chart_of_account_id' => $this->expenseAccount->id,
        ];

        $first = $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->post('/accounting/petty-cash/transactions', $payload);

        $first->assertRedirect();

        $second = $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->post('/accounting/petty-cash/transactions', $payload);

        $second->assertOk()
            ->assertJson([
                'idempotent' => true,
                'message' => 'Request already processed',
            ]);

        $this->assertSame(1, PettyCashTransaction::query()->count());
        $this->assertSame(1, IdempotencyKey::query()->where('key', $idempotencyKey)->count());
    }

    public function test_request_without_idempotency_key_is_not_deduplicated(): void
    {
        $payload = [
            'bank_account_id' => $this->pettyCashAccount->id,
            'direction' => 'out',
            'amount' => 150000,
            'transaction_date' => now()->toDateString(),
            'department_id' => $this->kitchen->id,
            'description' => 'Office supplies purchase',
            'chart_of_account_id' => $this->expenseAccount->id,
        ];

        $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post('/accounting/petty-cash/transactions', $payload)
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post('/accounting/petty-cash/transactions', $payload)
            ->assertRedirect();

        $this->assertSame(2, PettyCashTransaction::query()->count());
    }
}
