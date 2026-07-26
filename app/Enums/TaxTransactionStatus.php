<?php

namespace App\Enums;

enum TaxTransactionStatus: string
{
    case Unreported = 'unreported';
    case Reported = 'reported';

    public function label(): string
    {
        return match ($this) {
            self::Unreported => 'Unreported',
            self::Reported => 'Reported',
        };
    }
}
