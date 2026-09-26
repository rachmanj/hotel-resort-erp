<?php

namespace Tests\Unit;

use App\Enums\BankReconciliationStatus;
use App\Support\BankReconciliationSupport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BankReconciliationStatusTest extends TestCase
{
    #[DataProvider('editableStatuses')]
    public function test_is_editable_is_true_for_editable_statuses(BankReconciliationStatus $status): void
    {
        $this->assertTrue($status->isEditable());
    }

    /**
     * @return array<string, array{BankReconciliationStatus}>
     */
    public static function editableStatuses(): array
    {
        return [
            'draft' => [BankReconciliationStatus::Draft],
            'in_progress' => [BankReconciliationStatus::InProgress],
            'in_review' => [BankReconciliationStatus::InReview],
            'reopened' => [BankReconciliationStatus::Reopened],
        ];
    }

    #[DataProvider('nonEditableStatuses')]
    public function test_is_editable_is_false_for_non_editable_statuses(BankReconciliationStatus $status): void
    {
        $this->assertFalse($status->isEditable());
    }

    /**
     * @return array<string, array{BankReconciliationStatus}>
     */
    public static function nonEditableStatuses(): array
    {
        return [
            'pending_validation' => [BankReconciliationStatus::PendingValidation],
            'completed' => [BankReconciliationStatus::Completed],
            'failed' => [BankReconciliationStatus::Failed],
            'void' => [BankReconciliationStatus::Void],
            'processing' => [BankReconciliationStatus::Processing],
        ];
    }

    #[DataProvider('lockedStatuses')]
    public function test_is_locked_for_editing_is_true_only_for_locked_statuses(BankReconciliationStatus $status): void
    {
        $this->assertTrue($status->isLockedForEditing());
    }

    /**
     * @return array<string, array{BankReconciliationStatus}>
     */
    public static function lockedStatuses(): array
    {
        return [
            'pending_validation' => [BankReconciliationStatus::PendingValidation],
            'completed' => [BankReconciliationStatus::Completed],
            'void' => [BankReconciliationStatus::Void],
        ];
    }

    #[DataProvider('unlockedStatuses')]
    public function test_is_locked_for_editing_is_false_for_unlocked_statuses(BankReconciliationStatus $status): void
    {
        $this->assertFalse($status->isLockedForEditing());
    }

    /**
     * @return array<string, array{BankReconciliationStatus}>
     */
    public static function unlockedStatuses(): array
    {
        return [
            'draft' => [BankReconciliationStatus::Draft],
            'in_progress' => [BankReconciliationStatus::InProgress],
            'processing' => [BankReconciliationStatus::Processing],
            'in_review' => [BankReconciliationStatus::InReview],
            'failed' => [BankReconciliationStatus::Failed],
            'reopened' => [BankReconciliationStatus::Reopened],
        ];
    }

    public function test_every_status_has_a_non_empty_label(): void
    {
        foreach (BankReconciliationStatus::cases() as $status) {
            $this->assertNotSame('', trim($status->label()));
        }
    }

    public function test_line_hash_is_deterministic_and_sixty_four_characters(): void
    {
        $hash = BankReconciliationSupport::lineHash('2026-09-01', 'credit', 1_000_000, 'REF-1', 'Payment');

        $this->assertSame(64, strlen($hash));
        $this->assertSame(
            $hash,
            BankReconciliationSupport::lineHash('2026-09-01', 'credit', 1_000_000, 'REF-1', 'Payment'),
        );
    }

    public function test_line_hash_changes_when_inputs_change(): void
    {
        $base = BankReconciliationSupport::lineHash('2026-09-01', 'credit', 1_000_000, 'REF-1', 'Payment');

        $this->assertNotSame($base, BankReconciliationSupport::lineHash('2026-09-02', 'credit', 1_000_000, 'REF-1', 'Payment'));
        $this->assertNotSame($base, BankReconciliationSupport::lineHash('2026-09-01', 'debit', 1_000_000, 'REF-1', 'Payment'));
        $this->assertNotSame($base, BankReconciliationSupport::lineHash('2026-09-01', 'credit', 2_000_000, 'REF-1', 'Payment'));
        $this->assertNotSame($base, BankReconciliationSupport::lineHash('2026-09-01', 'credit', 1_000_000, 'REF-2', 'Payment'));
        $this->assertNotSame($base, BankReconciliationSupport::lineHash('2026-09-01', 'credit', 1_000_000, 'REF-1', 'Other'));
    }

    public function test_line_hash_differs_with_and_without_line_order(): void
    {
        $withoutOrder = BankReconciliationSupport::lineHash('2026-09-01', 'credit', 1_000_000, 'REF-1', 'Payment');
        $withOrder = BankReconciliationSupport::lineHash('2026-09-01', 'credit', 1_000_000, 'REF-1', 'Payment', 3);

        $this->assertNotSame($withoutOrder, $withOrder);
    }

    public function test_amounts_are_opposite_when_bank_debit_matches_book_credit(): void
    {
        $this->assertTrue(BankReconciliationSupport::amountsAreOpposite(100, 0, 0, 100));
    }

    public function test_amounts_are_not_opposite_when_both_are_debits(): void
    {
        $this->assertFalse(BankReconciliationSupport::amountsAreOpposite(100, 0, 100, 0));
    }

    public function test_amounts_are_opposite_within_tolerance(): void
    {
        $this->assertTrue(BankReconciliationSupport::amountsAreOpposite(100.004, 0, 0, 100));
    }

    public function test_amounts_are_not_opposite_beyond_tolerance(): void
    {
        $this->assertFalse(BankReconciliationSupport::amountsAreOpposite(100.01, 0, 0, 100));
    }
}
