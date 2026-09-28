<?php

namespace Tests\Feature;

use App\Actions\Accounting\ImportBankStatementAction;
use App\Enums\BankAccountType;
use App\Enums\BankReconciliationStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Models\User;
use App\Services\Accounting\BankReconciliation\Statement\BankStatementAiReader;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BankStatementAiReaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_ai_answer_consistent_with_summary_is_accepted(): void
    {
        config([
            'bank_reconciliation.ai.base_url' => 'https://ai.example.test/v1',
            'bank_reconciliation.ai.key' => 'test-key',
            'bank_reconciliation.ai.model' => 'test-model',
        ]);

        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'account_number' => '123',
                                'period_start' => '2026-07-01',
                                'period_end' => '2026-07-31',
                                'opening_balance' => 1000,
                                'closing_balance' => 1150,
                                'total_debit' => 50,
                                'total_credit' => 200,
                                'debit_count' => 1,
                                'credit_count' => 1,
                                'lines' => [
                                    [
                                        'posting_date' => '2026-07-01',
                                        'description' => 'Admin',
                                        'reference' => null,
                                        'debit' => 50,
                                        'credit' => 0,
                                        'balance' => 950,
                                        'line_order' => 0,
                                    ],
                                    [
                                        'posting_date' => '2026-07-02',
                                        'description' => 'Deposit',
                                        'reference' => null,
                                        'debit' => 0,
                                        'credit' => 200,
                                        'balance' => 1150,
                                        'line_order' => 1,
                                    ],
                                ],
                                'unparsed_rows' => ['count' => 0, 'reasons' => []],
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $reader = app(BankStatementAiReader::class);
        $statement = $reader->parse('ignored text');

        $this->assertCount(2, $statement->lines);
        $this->assertSame('pdf_ai', $statement->profileCode);
    }

    public function test_ai_answer_with_wrong_totals_is_rejected_on_import(): void
    {
        config([
            'bank_reconciliation.ai.base_url' => 'https://ai.example.test/v1',
            'bank_reconciliation.ai.key' => 'test-key',
        ]);

        Http::fake([
            'https://ai.example.test/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'account_number' => '123',
                                'period_start' => '2026-07-01',
                                'period_end' => '2026-07-31',
                                'opening_balance' => 1000,
                                'closing_balance' => 1150,
                                'total_debit' => 50,
                                'total_credit' => 200,
                                'debit_count' => 1,
                                'credit_count' => 1,
                                'lines' => [
                                    [
                                        'posting_date' => '2026-07-02',
                                        'description' => 'Deposit only',
                                        'debit' => 0,
                                        'credit' => 200,
                                        'balance' => 1200,
                                        'line_order' => 0,
                                    ],
                                ],
                                'unparsed_rows' => ['count' => 0, 'reasons' => []],
                            ]),
                        ],
                    ],
                ],
            ]),
        ]);

        $hotel = Hotel::query()->create([
            'name' => 'AI Hotel',
            'code' => 'AIH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $hotel->id]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $hotel->id,
            'account_code' => '1-1410',
            'name' => 'AI Bank GL',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::query()->create([
            'hotel_id' => $hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '999',
            'account_name' => 'AI',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['hotel_id' => $hotel->id]);
        $user->assignRole('finance');

        $reconciliation = BankReconciliation::factory()->for($bankAccount)->create([
            'status' => BankReconciliationStatus::InReview,
        ]);

        $text = file_get_contents(base_path('tests/Fixtures/bank-statements/bca_giro_unparsed.txt'));
        $pdf = Pdf::loadHTML('<pre>'.e($text).'</pre>');
        $path = tempnam(sys_get_temp_dir(), 'ai_stmt_').'.pdf';
        file_put_contents($path, $pdf->output());

        $action = app(ImportBankStatementAction::class);

        try {
            $action($reconciliation, $path, 'broken.pdf', 'bca_giro_pdf', false, $user, true);
            $this->fail('Expected import to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('line count mismatch', $exception->getMessage());
        }

        $this->assertDatabaseCount('bank_reconciliation_lines', 0);

        @unlink($path);
    }

    public function test_missing_api_key_disables_fallback_with_clear_message(): void
    {
        config([
            'bank_reconciliation.ai.key' => null,
        ]);

        $reader = app(BankStatementAiReader::class);

        $this->assertFalse($reader->isEnabled());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BANK_RECON_AI_KEY is not configured');

        $reader->parse('text');
    }
}
