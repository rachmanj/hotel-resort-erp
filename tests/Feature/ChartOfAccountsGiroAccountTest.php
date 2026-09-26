<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartOfAccountsGiroAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_of_accounts_seeder_creates_jasa_giro_revenue_account(): void
    {
        $hotel = Hotel::query()->create([
            'name' => 'Giro COA Hotel',
            'code' => 'GCH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $this->seed(ChartOfAccountsSeeder::class);

        $account = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where('account_code', '4-9100')
            ->first();

        $this->assertNotNull($account);
        $this->assertSame('Pendapatan Jasa Giro', $account->name);
        $this->assertSame(AccountType::Revenue, $account->account_type);
        $this->assertTrue($account->is_postable);
    }
}
