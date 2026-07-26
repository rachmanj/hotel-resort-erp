<?php

namespace App\Enums;

enum RestaurantTableArea: string
{
    case Indoor = 'indoor';
    case Poolside = 'poolside';
    case Terrace = 'terrace';

    public function label(): string
    {
        return match ($this) {
            self::Indoor => 'Indoor',
            self::Poolside => 'Poolside',
            self::Terrace => 'Terrace',
        };
    }
}
