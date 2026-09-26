<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\BankReconciliationStatus;
use App\Http\Controllers\Accounting\BankReconciliationController;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BankReconciliationReportTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private BankReconciliation $reconciliation;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Report Test Hotel',
            'code' => 'RPT',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '1-1510',
            'name' => 'Report Bank GL',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '9988776655',
            'account_name' => 'Report Account',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $this->reconciliation = BankReconciliation::factory()->for($bankAccount)->create([
            'period_end_date' => '2026-09-30',
            'periode' => '2026-09-01',
            'statement_opening_balance' => 0,
            'statement_closing_balance' => 2_500_000,
            'book_closing_balance' => 2_500_000,
            'status' => BankReconciliationStatus::InReview,
        ]);
    }

    public function test_report_page_renders_for_bankrec_view_and_is_denied_without_permission(): void
    {
        $viewer = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $viewer->assignRole('front_office');
        $this->hotel->users()->attach($viewer->id);

        $this->actingAs($viewer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('accounting.bank-rec.report', $this->reconciliation))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/BankReconciliation/Report')
                ->has('header')
                ->has('balanceProof')
                ->has('statementItems')
                ->has('bookItems')
                ->has('adjustments')
                ->has('signOff')
                ->where('header.account_no', '9988776655'));

        $denied = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $denied->assignRole('housekeeping');
        $this->hotel->users()->attach($denied->id);

        $this->actingAs($denied)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('accounting.bank-rec.report', $this->reconciliation))
            ->assertForbidden();
    }

    public function test_report_pdf_download_returns_pdf_with_expected_content(): void
    {
        $viewer = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $viewer->assignRole('finance');
        $this->hotel->users()->attach($viewer->id);

        $response = $this->actingAs($viewer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('accounting.bank-rec.report.download', $this->reconciliation));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $controller = app(BankReconciliationController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('buildReportDocumentData');
        $method->setAccessible(true);
        $document = $method->invoke($controller, $this->reconciliation->fresh());

        $rendered = view('bank-reconciliation.report', $document)->render();

        $this->assertStringContainsString('9988776655', $rendered);
        $this->assertStringContainsString('2026-09-30', $rendered);
        $this->assertStringContainsString('2.500.000,00', $rendered);
    }

    public function test_status_json_retains_documented_keys_after_ui_prop_work(): void
    {
        $viewer = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $viewer->assignRole('finance');
        $this->hotel->users()->attach($viewer->id);

        $this->actingAs($viewer)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->getJson(route('accounting.bank-rec.status', $this->reconciliation))
            ->assertOk()
            ->assertJsonStructure([
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
}
