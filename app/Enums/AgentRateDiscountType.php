<?php

namespace App\Enums;

enum AgentRateDiscountType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percent',
            self::Fixed => 'Fixed Amount',
        };
    }
}
