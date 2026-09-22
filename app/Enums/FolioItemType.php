<?php

namespace App\Enums;

enum FolioItemType: string
{
    case Room = 'room';
    case Fb = 'fb';
    case Spa = 'spa';
    case Misc = 'misc';
    case Tax = 'tax';
    case ServiceCharge = 'service_charge';
    case Discount = 'discount';
    case DepositCredit = 'deposit_credit';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'Room',
            self::Fb => 'F&B',
            self::Spa => 'Spa',
            self::Misc => 'Miscellaneous',
            self::Tax => 'Tax',
            self::ServiceCharge => 'Service Charge',
            self::Discount => 'Discount',
            self::DepositCredit => 'Deposit Credit',
        };
    }

    /**
     * Only Room and F&B revenue carries service charge and tax. Dive center,
     * boat trips, rentals, laundry, spa, and every other misc charge are sold
     * inclusive of everything and must be posted with no tax breakdown.
     */
    public function isTaxable(): bool
    {
        return match ($this) {
            self::Room, self::Fb => true,
            self::Spa, self::Misc, self::Tax, self::ServiceCharge, self::Discount, self::DepositCredit => false,
        };
    }
}
