<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\BankReconciliationStatus;
use App\Enums\BankStatementLineStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationAudit;
use App\Models\BankReconciliationLine;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BankReconciliationImportFileTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $financeUser;

    private BankReconciliation $reconciliation;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Import Hotel',
            'code' => 'IMP',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $this->hotel->id]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '1-1400',
            'name' => 'Import Bank GL',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '1234567890',
            'account_name' => 'Import',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $this->financeUser = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->financeUser->assignRole('finance');
        $this->hotel->users()->attach($this->financeUser->id);

        $this->reconciliation = BankReconciliation::factory()->for($bankAccount)->create([
            'status' => BankReconciliationStatus::InReview,
            'period_end_date' => '2026-07-31',
            'periode' => '2026-07-01',
        ]);
    }

    public function test_preview_dry_run_writes_nothing(): void
    {
        $file = $this->makeSamplePdfUpload();

        $response = $this->actingAs($this->financeUser)->postJson(
            route('accounting.bank-rec.import-preview', $this->reconciliation),
            ['file' => $file, 'profile_code' => 'bca_giro_pdf'],
        );

        $response->assertOk();
        $this->assertDatabaseCount('bank_reconciliation_lines', 0);
    }

    public function test_commit_writes_lines_and_statement_columns(): void
    {
        $file = $this->makeSamplePdfUpload();

        $response = $this->actingAs($this->financeUser)->post(
            route('accounting.bank-rec.import-file', $this->reconciliation),
            ['file' => $file, 'profile_code' => 'bca_giro_pdf', 'replace' => true],
        );

        $response->assertRedirect();
        $this->reconciliation->refresh();

        $this->assertSame('bca_giro_pdf', $this->reconciliation->statement_format);
        $this->assertNotNull($this->reconciliation->statement_hash);
        $this->assertSame(1670000.0, (float) $this->reconciliation->statement_closing_balance);
        $this->assertDatabaseCount('bank_reconciliation_lines', 3);
    }

    public function test_reimporting_same_file_adds_zero_rows(): void
    {
        $file = $this->makeSamplePdfUpload();
        $path = $file->getRealPath();

        $this->actingAs($this->financeUser)->post(
            route('accounting.bank-rec.import-file', $this->reconciliation),
            ['file' => $file, 'profile_code' => 'bca_giro_pdf', 'replace' => true],
        );

        $secondUpload = new UploadedFile($path, 'statement.pdf', 'application/pdf', null, true);

        $response = $this->actingAs($this->financeUser)->post(
            route('accounting.bank-rec.import-file', $this->reconciliation),
            ['file' => $secondUpload, 'profile_code' => 'bca_giro_pdf', 'replace' => true],
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bank_reconciliation_lines', 3);
    }

    public function test_import_refused_when_matched_lines_exist(): void
    {
        BankReconciliationLine::factory()->for($this->reconciliation)->create([
            'match_status' => BankStatementLineStatus::Matched,
        ]);

        $file = $this->makeSamplePdfUpload();

        $response = $this->actingAs($this->financeUser)->post(
            route('accounting.bank-rec.import-file', $this->reconciliation),
            ['file' => $file, 'profile_code' => 'bca_giro_pdf'],
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bank_reconciliation_lines', 1);
    }

    public function test_invalid_totals_refused_with_nothing_written(): void
    {
        $text = file_get_contents(base_path('tests/Fixtures/bank-statements/bca_giro_unparsed.txt'));
        $file = $this->makePdfUploadFromText($text);

        $response = $this->actingAs($this->financeUser)->post(
            route('accounting.bank-rec.import-file', $this->reconciliation),
            ['file' => $file, 'profile_code' => 'bca_giro_pdf', 'replace' => true],
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('bank_reconciliation_lines', 0);
    }

    public function test_user_without_permission_gets_403(): void
    {
        $viewer = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $viewer->assignRole('front_office');
        $this->hotel->users()->attach($viewer->id);

        $file = $this->makeSamplePdfUpload();

        $this->actingAs($viewer)->post(
            route('accounting.bank-rec.import-file', $this->reconciliation),
            ['file' => $file],
        )->assertForbidden();
    }

    public function test_audit_row_imported_exists(): void
    {
        $file = $this->makeSamplePdfUpload();

        $this->actingAs($this->financeUser)->post(
            route('accounting.bank-rec.import-file', $this->reconciliation),
            ['file' => $file, 'profile_code' => 'bca_giro_pdf', 'replace' => true],
        );

        $this->assertDatabaseHas('bank_reconciliation_audits', [
            'bank_reconciliation_id' => $this->reconciliation->id,
            'action' => 'imported',
            'performed_by' => $this->financeUser->id,
        ]);

        $audit = BankReconciliationAudit::query()->where('action', 'imported')->first();
        $this->assertSame(3, $audit?->amounts['line_count']);
    }

    private function makeSamplePdfUpload(): UploadedFile
    {
        $text = file_get_contents(base_path('tests/Fixtures/bank-statements/bca_giro_sample.txt'));

        return $this->makePdfUploadFromText($text);
    }

    private function makePdfUploadFromText(string $text): UploadedFile
    {
        $pdf = Pdf::loadHTML('<pre style="font-family: monospace; font-size: 10px;">'.e($text).'</pre>');
        $path = tempnam(sys_get_temp_dir(), 'bank_stmt_test_').'.pdf';
        file_put_contents($path, $pdf->output());

        return new UploadedFile($path, 'statement.pdf', 'application/pdf', null, true);
    }
}
