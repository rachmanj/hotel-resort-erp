<?php

namespace App\Enums;

enum DepreciationMethod: string
{
    case StraightLine = 'straight_line';
    case DoubleDeclining = 'double_declining';

    public function label(): string
    {
        return match ($this) {
            self::StraightLine => 'Straight Line',
            self::DoubleDeclining => 'Double Declining',
        };
    }
}
