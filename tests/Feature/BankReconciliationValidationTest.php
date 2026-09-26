<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Enums\BankReconciliationStatus;
use App\Enums\BankReconciliationValidationStatus;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationAudit;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Models\User;
use App\Notifications\BankReconciliationRejectedNotification;
use App\Notifications\BankReconciliationSubmittedNotification;
use App\Services\Accounting\BankReconciliation\BankReconciliationWorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BankReconciliationValidationTest extends TestCase
{
    use RefreshDatabase;

    private BankReconciliationWorkflowService $workflow;

    private Hotel $hotel;

    private BankAccount $bankAccount;

    private User $preparer;

    private User $validator;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->workflow = app(BankReconciliationWorkflowService::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Validation Hotel',
            'code' => 'VLH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $this->hotel->id]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '1-1310',
            'name' => 'Validation Bank GL',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '9988776655',
            'account_name' => 'Validation Account',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);

        $this->preparer = User::factory()->create(['name' => 'Preparer User']);
        $this->preparer->assignRole('front_office');
        $this->hotel->users()->attach($this->preparer->id);

        $this->validator = User::factory()->create(['name' => 'Validator User']);
        $this->validator->assignRole('finance');
        $this->hotel->users()->attach($this->validator->id);
    }

    public function test_submit_for_validation_refuses_unmatched_lines(): void
    {
        $reconciliation = $this->makeBalancedReconciliation();

        BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 0,
            'credit' => 50_000,
        ]);

        BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 25_000,
            'credit' => 0,
        ]);

        try {
            $this->workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $this->preparer);
            $this->fail('Expected InvalidArgumentException for unmatched lines.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Unmatched', $exception->getMessage());
        }
    }

    public function test_submit_for_validation_refuses_missing_statement_balances(): void
    {
        $reconciliation = $this->makeBalancedReconciliation([
            'statement_opening_balance' => null,
            'statement_closing_balance' => null,
        ]);

        try {
            $this->workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $this->preparer);
            $this->fail('Expected InvalidArgumentException for missing balances.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Closing balances are required', $exception->getMessage());
        }
    }

    public function test_submit_for_validation_refuses_when_cleared_nets_do_not_offset(): void
    {
        $reconciliation = $this->makeBalancedReconciliation();

        BankReconciliationLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 100,
            'credit' => 0,
        ]);

        BankReconciliationBookLine::factory()->matched()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'debit' => 100,
            'credit' => 0,
        ]);

        try {
            $this->workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $this->preparer);
            $this->fail('Expected InvalidArgumentException for cleared difference.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Cleared lines do not net to zero', $exception->getMessage());
        }
    }

    public function test_submit_for_validation_refuses_when_book_line_is_stale(): void
    {
        $reconciliation = $this->makeBalancedReconciliation();
        $bookLine = $reconciliation->bookLines()->firstOrFail();
        $bookLine->update([
            'is_stale' => true,
            'stale_reason' => 'Ledger debit changed after snapshot.',
        ]);

        try {
            $this->workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $this->preparer);
            $this->fail('Expected InvalidArgumentException for stale book lines.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('stale', strtolower($exception->getMessage()));
            $this->assertStringContainsString('reload', strtolower($exception->getMessage()));
        }
    }

    public function test_submit_for_validation_succeeds_on_balanced_session(): void
    {
        Notification::fake();

        $reconciliation = $this->makeBalancedReconciliation();

        $result = $this->workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $this->preparer);

        $this->assertSame(BankReconciliationStatus::PendingValidation, $result->status);
        $this->assertSame(BankReconciliationValidationStatus::Pending, $result->validation_status);
        $this->assertSame($this->preparer->id, $result->submitted_by);
        $this->assertNotNull($result->submitted_at);
        $this->assertNull($result->rejection_reason);

        $this->assertTrue(
            BankReconciliationAudit::query()
                ->where('bank_reconciliation_id', $reconciliation->id)
                ->where('action', 'submitted')
                ->exists()
        );

        Notification::assertSentTo($this->validator, BankReconciliationSubmittedNotification::class);
        Notification::assertNotSentTo($this->preparer, BankReconciliationSubmittedNotification::class);
    }

    public function test_validate_reconciliation_by_different_user_completes_session(): void
    {
        Notification::fake();

        $reconciliation = $this->makeBalancedReconciliation();
        $submitted = $this->workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $this->preparer);

        $result = $this->workflow->validateReconciliation($submitted->fresh(), $this->validator);

        $this->assertSame(BankReconciliationStatus::Completed, $result->status);
        $this->assertSame(BankReconciliationValidationStatus::Validated, $result->validation_status);
        $this->assertSame($this->validator->id, $result->validated_by);
        $this->assertNotNull($result->validated_at);
        $this->assertNotNull($result->finalized_at);
    }

    public function test_validate_reconciliation_by_preparer_is_refused(): void
    {
        Notification::fake();

        $preparerValidator = User::factory()->create(['name' => 'Preparer Validator']);
        $preparerValidator->assignRole('finance');
        $this->hotel->users()->attach($preparerValidator->id);

        $reconciliation = $this->makeBalancedReconciliation(['created_by' => $preparerValidator->id]);
        $submitted = $this->workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $preparerValidator);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot validate your own');

        $this->workflow->validateReconciliation($submitted->fresh(), $preparerValidator);
    }

    public function test_reject_reconciliation_reopens_session_for_editing_and_notifies_preparer(): void
    {
        Notification::fake();

        $reconciliation = $this->makeBalancedReconciliation();
        $submitted = $this->workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $this->preparer);

        try {
            $this->workflow->rejectReconciliation($submitted->fresh(), $this->validator, '   ');
            $this->fail('Expected InvalidArgumentException for blank rejection reason.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('reason', strtolower($exception->getMessage()));
        }

        $result = $this->workflow->rejectReconciliation($submitted->fresh(), $this->validator, 'Missing support documents');

        $this->assertSame(BankReconciliationStatus::InReview, $result->status);
        $this->assertSame(BankReconciliationValidationStatus::Rejected, $result->validation_status);
        $this->assertSame('Missing support documents', $result->rejection_reason);
        $this->assertNull($result->submitted_by);
        $this->assertNull($result->submitted_at);
        $this->assertFalse($result->isLockedForEditing());

        Notification::assertSentTo($this->preparer, BankReconciliationRejectedNotification::class);
        Notification::assertNotSentTo($this->validator, BankReconciliationRejectedNotification::class);
    }

    public function test_reopen_reconciliation_from_completed_with_reason(): void
    {
        Notification::fake();

        $reconciliation = $this->makeBalancedReconciliation();
        $submitted = $this->workflow->submitForValidation($reconciliation->fresh(['lines', 'bookLines']), $this->preparer);
        $completed = $this->workflow->validateReconciliation($submitted->fresh(), $this->validator);

        try {
            $this->workflow->reopenReconciliation($completed->fresh(), $this->validator, '   ');
            $this->fail('Expected InvalidArgumentException for blank reopen reason.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('reason', strtolower($exception->getMessage()));
        }

        $reopened = $this->workflow->reopenReconciliation($completed->fresh(), $this->validator, 'Bank corrected statement');

        $this->assertSame(BankReconciliationStatus::InReview, $reopened->status);
        $this->assertNull($reopened->validation_status);
        $this->assertSame('Bank corrected statement', $reopened->reopen_reason);
        $this->assertNull($reopened->validated_by);
        $this->assertNull($reopened->validated_at);
        $this->assertNull($reopened->finalized_at);
        $this->assertFalse($reopened->isLockedForEditing());

        $this->assertTrue(
            BankReconciliationAudit::query()
                ->where('bank_reconciliation_id', $reconciliation->id)
                ->where('action', 'reopened')
                ->exists()
        );

        $inReview = $this->makeBalancedReconciliation([
            'status' => BankReconciliationStatus::InReview,
            'period_end_date' => '2026-10-31',
            'periode' => '2026-10-01',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->workflow->reopenReconciliation($inReview->fresh(['lines', 'bookLines']), $this->validator, 'Too early');
    }

    public function test_status_payload_for_exposes_documented_keys(): void
    {
        $reconciliation = $this->makeBalancedReconciliation();
        $reconciliation->load(['lines', 'bookLines', 'matches']);

        $payload = $this->workflow->statusPayloadFor($reconciliation);

        $this->assertIsFloat($payload['bank_net']);
        $this->assertIsBool($payload['is_balanced']);
        $this->assertSame(BankReconciliationStatus::InReview->value, $payload['status']);
        $this->assertIsString($payload['status_label']);
        $this->assertArrayHasKey('validation_status', $payload);
        $this->assertSame(1, $payload['bank_lines_count']);
        $this->assertSame(1, $payload['book_lines_count']);
        $this->assertSame(0, $payload['match_groups_count']);
        $this->assertSame(0, $payload['stale_lines_count']);
        $this->assertIsInt($payload['outstanding_attention_count']);
        $this->assertArrayHasKey('rejection_reason', $payload);
        $this->assertArrayHasKey('submitted_at', $payload);
        $this->assertArrayHasKey('validated_at', $payload);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeBalancedReconciliation(array $overrides = []): BankReconciliation
    {
        $reconciliation = BankReconciliation::factory()
            ->for($this->bankAccount)
            ->create(array_merge([
                'period_end_date' => '2026-09-30',
                'periode' => '2026-09-01',
                'statement_opening_balance' => 0,
                'statement_closing_balance' => 1_000_000,
                'book_closing_balance' => 1_000_000,
                'status' => BankReconciliationStatus::InReview,
                'created_by' => $this->preparer->id,
            ], $overrides));

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
