<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Transfer = 'transfer';
    case EwalletQris = 'ewallet_qris';
    case CityLedger = 'city_ledger';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Transfer => 'Bank Transfer',
            self::EwalletQris => 'E-Wallet / QRIS',
            self::CityLedger => 'City Ledger',
        };
    }
}
