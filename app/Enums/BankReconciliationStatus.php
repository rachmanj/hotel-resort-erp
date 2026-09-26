<?php

namespace App\Enums;

enum BankReconciliationStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Processing = 'processing';
    case InReview = 'in_review';
    case PendingValidation = 'pending_validation';
    case Completed = 'completed';
    case Failed = 'failed';
    case Reopened = 'reopened';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InProgress => 'In Progress',
            self::Processing => 'Processing',
            self::InReview => 'In Review',
            self::PendingValidation => 'Pending Validation',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Reopened => 'Reopened',
            self::Void => 'Void',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [
            self::Draft,
            self::InProgress,
            self::InReview,
            self::Reopened,
        ], true);
    }

    public function isLockedForEditing(): bool
    {
        return in_array($this, [
            self::PendingValidation,
            self::Completed,
            self::Void,
        ], true);
    }
}
