<?php

namespace App\Services\Accounting\BankReconciliation;

use App\Actions\Accounting\CarryForwardOutstandingAction;
use App\Enums\BankReconciliationStatus;
use App\Enums\BankReconciliationValidationStatus;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationAudit;
use App\Models\User;
use App\Support\BankReconciliationSupport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankReconciliationWorkflowService
{
    public function __construct(
        private BankReconciliationBalanceService $balanceService,
        private BankBookLineFetcher $bookLineFetcher,
        private BankReconciliationNotifier $notifier,
        private CarryForwardOutstandingAction $carryForwardOutstandingAction,
    ) {}

    public function submitForValidation(BankReconciliation $reconciliation, User $actor): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $actor): BankReconciliation {
            $reconciliation->refresh();

            if ($reconciliation->status === BankReconciliationStatus::PendingValidation) {
                throw new InvalidArgumentException('This bank reconciliation is already pending validation.');
            }

            if ($reconciliation->status === BankReconciliationStatus::Completed) {
                throw new InvalidArgumentException('This bank reconciliation is already completed.');
            }

            if ($reconciliation->validation_status === BankReconciliationValidationStatus::Pending) {
                throw new InvalidArgumentException('This bank reconciliation is already awaiting validation.');
            }

            if ($this->balanceService->incomplete($reconciliation)) {
                throw new InvalidArgumentException($this->balanceService->diagnostic($reconciliation));
            }

            $unmatchedBank = $this->balanceService->unmatchedBankCount($reconciliation);
            $unmatchedBook = $this->balanceService->unmatchedBookCount($reconciliation);

            if ($unmatchedBank > 0 || $unmatchedBook > 0) {
                throw new InvalidArgumentException(sprintf(
                    'All statement and book lines must be matched, excluded, marked outstanding, or adjusted before submission. Unmatched lines: %d statement / %d book.',
                    $unmatchedBank,
                    $unmatchedBook,
                ));
            }

            if (abs($this->balanceService->difference($reconciliation)) >= BankReconciliationSupport::TOLERANCE) {
                throw new InvalidArgumentException(sprintf(
                    'Cleared lines do not net to zero (difference %s). Match or reclassify lines until the cleared difference is zero.',
                    number_format($this->balanceService->difference($reconciliation), 2, '.', ','),
                ));
            }

            $crossFoot = $this->balanceService->crossFoot($reconciliation);
            if ($crossFoot === false) {
                throw new InvalidArgumentException('Statement movement does not cross-foot with the opening and closing balances. Review statement lines and balances.');
            }

            if (abs($this->balanceService->unexplainedDifference($reconciliation)) >= BankReconciliationSupport::TOLERANCE) {
                throw new InvalidArgumentException(sprintf(
                    'Unexplained difference %s remains after matching. Resolve outstanding items or adjust before submitting.',
                    number_format($this->balanceService->unexplainedDifference($reconciliation), 2, '.', ','),
                ));
            }

            $this->bookLineFetcher->refreshStaleFlags($reconciliation);
            $staleCount = $this->bookLineFetcher->staleLines($reconciliation)->count();

            if ($staleCount > 0) {
                throw new InvalidArgumentException(sprintf(
                    '%d book line(s) are stale because the general ledger changed after this session was loaded. Reload the book side before submitting.',
                    $staleCount,
                ));
            }

            $reconciliation->update([
                'validation_status' => BankReconciliationValidationStatus::Pending,
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'status' => BankReconciliationStatus::PendingValidation,
                'rejection_reason' => null,
            ]);

            $this->writeAudit($reconciliation, 'submitted', $actor);

            $this->notifier->notifyValidators($reconciliation->fresh(), $actor);

            return $reconciliation->fresh();
        });
    }

    public function validateReconciliation(BankReconciliation $reconciliation, User $validator): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $validator): BankReconciliation {
            $reconciliation->refresh();

            $this->assertPendingValidation($reconciliation);

            if ($reconciliation->status === BankReconciliationStatus::Completed) {
                throw new InvalidArgumentException('This bank reconciliation is already completed.');
            }

            if ($reconciliation->isPreparer($validator->id)) {
                throw new InvalidArgumentException('You cannot validate your own bank reconciliation submission. Another user with validation permission must approve it.');
            }

            $now = now();

            $reconciliation->update([
                'validation_status' => BankReconciliationValidationStatus::Validated,
                'validated_by' => $validator->id,
                'validated_at' => $now,
                'finalized_at' => $now,
                'status' => BankReconciliationStatus::Completed,
            ]);

            $this->writeAudit($reconciliation, 'validated', $validator);

            return $reconciliation->fresh();
        });
    }

    public function rejectReconciliation(BankReconciliation $reconciliation, User $validator, string $reason): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $validator, $reason): BankReconciliation {
            $reconciliation->refresh();

            $this->assertPendingValidation($reconciliation);

            if ($reconciliation->status === BankReconciliationStatus::Completed) {
                throw new InvalidArgumentException('This bank reconciliation is already completed.');
            }

            if ($reconciliation->isPreparer($validator->id)) {
                throw new InvalidArgumentException('You cannot validate your own bank reconciliation submission. Another user with validation permission must approve it.');
            }

            $trimmedReason = trim($reason);
            if ($trimmedReason === '') {
                throw new InvalidArgumentException('A reason is required when rejecting a bank reconciliation.');
            }

            $this->notifier->notifyPreparer($reconciliation, $validator, $trimmedReason);

            $reconciliation->update([
                'validation_status' => BankReconciliationValidationStatus::Rejected,
                'rejection_reason' => $trimmedReason,
                'validated_by' => $validator->id,
                'validated_at' => now(),
                'status' => BankReconciliationStatus::InReview,
                'submitted_by' => null,
                'submitted_at' => null,
            ]);

            $this->writeAudit($reconciliation, 'rejected', $validator, $trimmedReason);

            return $reconciliation->fresh();
        });
    }

    public function reopenReconciliation(BankReconciliation $reconciliation, User $actor, string $reason): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliation, $actor, $reason): BankReconciliation {
            $reconciliation->refresh();

            if ($reconciliation->status !== BankReconciliationStatus::Completed) {
                throw new InvalidArgumentException('Only a completed bank reconciliation can be reopened.');
            }

            $trimmedReason = trim($reason);
            if ($trimmedReason === '') {
                throw new InvalidArgumentException('A reason is required when reopening a bank reconciliation.');
            }

            $reconciliation->update([
                'status' => BankReconciliationStatus::InReview,
                'validation_status' => null,
                'reopen_reason' => $trimmedReason,
                'validated_by' => null,
                'validated_at' => null,
                'finalized_at' => null,
            ]);

            $this->writeAudit($reconciliation, 'reopened', $actor, $trimmedReason);

            return $reconciliation->fresh();
        });
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public function statusPayloadFor(BankReconciliation $reconciliation): array
    {
        $reconciliation->loadCount(['lines', 'bookLines', 'matches']);
        $reconciliation->loadMissing(['lines', 'bookLines']);

        $payload = $this->balanceService->statusPayload($reconciliation);

        $payload['status'] = $reconciliation->status->value;
        $payload['status_label'] = $reconciliation->status->label();
        $payload['validation_status'] = $reconciliation->validation_status?->value;
        $payload['bank_lines_count'] = $reconciliation->lines_count;
        $payload['book_lines_count'] = $reconciliation->book_lines_count;
        $payload['match_groups_count'] = $reconciliation->matches_count;
        $payload['stale_lines_count'] = $this->bookLineFetcher->staleLines($reconciliation)->count();
        $payload['rejection_reason'] = $reconciliation->rejection_reason;
        $payload['submitted_at'] = $reconciliation->submitted_at?->toIso8601String();
        $payload['validated_at'] = $reconciliation->validated_at?->toIso8601String();
        $payload['outstanding_attention_count'] = $this->carryForwardOutstandingAction
            ->outstandingNeedingAttention($reconciliation)
            ->count();

        return $payload;
    }

    private function assertPendingValidation(BankReconciliation $reconciliation): void
    {
        if ($reconciliation->validation_status !== BankReconciliationValidationStatus::Pending) {
            throw new InvalidArgumentException('This bank reconciliation is not pending validation.');
        }
    }

    private function writeAudit(
        BankReconciliation $reconciliation,
        string $action,
        User $actor,
        ?string $notes = null,
    ): void {
        BankReconciliationAudit::query()->create([
            'bank_reconciliation_id' => $reconciliation->id,
            'bank_reconciliation_match_id' => null,
            'action' => $action,
            'bank_line_ids' => [],
            'book_line_ids' => [],
            'amounts' => [],
            'performed_by' => $actor->id,
            'notes' => $notes,
        ]);
    }
}
