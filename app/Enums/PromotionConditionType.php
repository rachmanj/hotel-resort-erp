<?php

namespace App\Enums;

enum PromotionConditionType: string
{
    case DayOfWeek = 'day_of_week';
    case BlackoutDate = 'blackout_date';
    case MinLos = 'min_los';
    case MaxLos = 'max_los';

    public function label(): string
    {
        return match ($this) {
            self::DayOfWeek => 'Day of Week',
            self::BlackoutDate => 'Blackout Date',
            self::MinLos => 'Minimum Length of Stay',
            self::MaxLos => 'Maximum Length of Stay',
        };
    }
}
