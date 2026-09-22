<?php

namespace App\Enums;

enum ProformaPaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Transfer Bank',
        };
    }
}
