<?php

namespace App\Enums;

enum BankBookLineStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Manual = 'manual';
    case Excluded = 'excluded';
    case Outstanding = 'outstanding';

    public function label(): string
    {
        return match ($this) {
            self::Unmatched => 'Unmatched',
            self::Matched => 'Matched',
            self::Manual => 'Manual',
            self::Excluded => 'Excluded',
            self::Outstanding => 'Outstanding',
        };
    }
}
