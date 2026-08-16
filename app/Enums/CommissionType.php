<?php

namespace App\Enums;

enum CommissionType: string
{
    case Percent = 'percent';
    case Flat = 'flat';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percent',
            self::Flat => 'Flat',
        };
    }
}
