<?php

namespace App\Enums;

enum HousekeepingShift: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Night = 'night';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Morning',
            self::Afternoon => 'Afternoon',
            self::Night => 'Night',
        };
    }
}
