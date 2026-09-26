<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\BankReconciliationStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\BankReconciliationMatch;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BankReconciliationRoutesTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $financeUser;

    private BankReconciliation $reconciliation;

    private BankReconciliationLine $statementLine;

    private BankReconciliationBookLine $bookLine;

    private BankReconciliationMatch $match;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Route Test Hotel',
            'code' => 'RTH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '1-1500',
            'name' => 'Route Bank GL',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '5566778899',
            'account_name' => 'Route Account',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $this->financeUser = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->financeUser->assignRole('finance');
        $this->hotel->users()->attach($this->financeUser->id);

        $this->reconciliation = BankReconciliation::factory()->for($bankAccount)->create([
            'period_end_date' => '2026-09-30',
            'periode' => '2026-09-01',
            'statement_opening_balance' => 0,
            'statement_closing_balance' => 1_000_000,
            'book_closing_balance' => 1_000_000,
            'status' => BankReconciliationStatus::InReview,
            'created_by' => $this->financeUser->id,
        ]);

        $this->statementLine = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $this->reconciliation->id,
        ]);

        $this->bookLine = BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $this->reconciliation->id,
        ]);

        $this->match = BankReconciliationMatch::factory()->create([
            'bank_reconciliation_id' => $this->reconciliation->id,
        ]);
    }

    public function test_named_bank_reconciliation_routes_resolve_to_expected_uris(): void
    {
        $routes = [
            ['accounting.bank-rec.index', [], '/accounting/bank-reconciliation'],
            ['accounting.bank-rec.store', [], '/accounting/bank-reconciliation'],
            ['accounting.bank-rec.reconcile', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/reconcile'],
            ['accounting.bank-rec.status', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/status'],
            ['accounting.bank-rec.import-lines', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/import-lines'],
            ['accounting.bank-rec.balances', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/balances'],
            ['accounting.bank-rec.book-lines.refresh', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/book-lines/refresh'],
            ['accounting.bank-rec.auto-match', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/auto-match'],
            ['accounting.bank-rec.match', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/match'],
            ['accounting.bank-rec.unmatch', ['bankReconciliation' => $this->reconciliation, 'match' => $this->match], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/match/'.$this->match->id],
            ['accounting.bank-rec.lines.exclude', ['bankReconciliation' => $this->reconciliation, 'line' => $this->statementLine], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/lines/'.$this->statementLine->id.'/exclude'],
            ['accounting.bank-rec.lines.include', ['bankReconciliation' => $this->reconciliation, 'line' => $this->statementLine], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/lines/'.$this->statementLine->id.'/include'],
            ['accounting.bank-rec.lines.outstanding', ['bankReconciliation' => $this->reconciliation, 'line' => $this->statementLine], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/lines/'.$this->statementLine->id.'/outstanding'],
            ['accounting.bank-rec.book-lines.exclude', ['bankReconciliation' => $this->reconciliation, 'bookLine' => $this->bookLine], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/book-lines/'.$this->bookLine->id.'/exclude'],
            ['accounting.bank-rec.book-lines.include', ['bankReconciliation' => $this->reconciliation, 'bookLine' => $this->bookLine], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/book-lines/'.$this->bookLine->id.'/include'],
            ['accounting.bank-rec.book-lines.outstanding', ['bankReconciliation' => $this->reconciliation, 'bookLine' => $this->bookLine], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/book-lines/'.$this->bookLine->id.'/outstanding'],
            ['accounting.bank-rec.adjustments.store', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/adjustments'],
            ['accounting.bank-rec.adjustments.reverse', ['bankReconciliation' => $this->reconciliation, 'line' => $this->statementLine], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/adjustments/'.$this->statementLine->id.'/reverse'],
            ['accounting.bank-rec.submit', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/submit'],
            ['accounting.bank-rec.validate', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/validate'],
            ['accounting.bank-rec.reject', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/reject'],
            ['accounting.bank-rec.reopen', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/reopen'],
            ['accounting.bank-rec.void', ['bankReconciliation' => $this->reconciliation], '/accounting/bank-reconciliation/'.$this->reconciliation->id.'/void'],
        ];

        foreach ($routes as [$name, $parameters, $expectedUri]) {
            $this->assertSame($expectedUri, route($name, $parameters, false));
        }
    }

    public function test_post_adjustment_without_counter_account_returns_validation_error(): void
    {
        $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.adjustments.store', $this->reconciliation), [
                'statement_line_id' => $this->statementLine->id,
                'description' => 'Missing counter account',
            ])
            ->assertSessionHasErrors(['counter_account_id'])
            ->assertStatus(302);
    }

    public function test_reject_without_reason_returns_validation_error(): void
    {
        $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('accounting.bank-rec.reject', $this->reconciliation), [])
            ->assertSessionHasErrors(['reason'])
            ->assertStatus(302);
    }

    public function test_status_returns_json_with_documented_keys(): void
    {
        $response = $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->getJson(route('accounting.bank-rec.status', $this->reconciliation));

        $response->assertOk();
        $response->assertJsonStructure([
            'bank_net',
            'book_net',
            'difference',
            'is_balanced',
            'status',
            'status_label',
            'validation_status',
            'bank_lines_count',
            'book_lines_count',
            'match_groups_count',
            'stale_lines_count',
            'outstanding_attention_count',
            'rejection_reason',
            'submitted_at',
            'validated_at',
        ]);
    }

    public function test_index_and_reconcile_pages_render_for_bankrec_view_user(): void
    {
        $viewer = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $viewer->assignRole('front_office');
        $this->hotel->users()->attach($viewer->id);

        $this->actingAs($viewer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('accounting.bank-rec.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/BankReconciliation/Index'));

        $this->actingAs($viewer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('accounting.bank-rec.reconcile', $this->reconciliation))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/BankReconciliation/Reconcile'));
    }
}
