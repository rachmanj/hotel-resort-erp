<?php

namespace App\Enums;

enum PromoDiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case PackagePrice = 'package_price';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentage',
            self::Fixed => 'Fixed Amount',
            self::PackagePrice => 'Package Price',
        };
    }
}
