<?php

namespace App\Enums;

enum BankAccountType: string
{
    case Bank = 'bank';
    case PettyCash = 'petty_cash';

    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Bank',
            self::PettyCash => 'Petty Cash',
        };
    }
}
