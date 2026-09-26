<?php

namespace App\Enums;

enum BankReconciliationValidationStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Validated => 'Validated',
            self::Rejected => 'Rejected',
        };
    }
}
