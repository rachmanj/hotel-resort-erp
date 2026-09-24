<?php

namespace App\Enums;

enum DailyTripBoatClass: string
{
    case Small40Pk = 'small_40pk';
    case Medium200Pk = 'medium_200pk';

    public function label(): string
    {
        return match ($this) {
            self::Small40Pk => 'Small Boat (2–3 pax, 40 PK)',
            self::Medium200Pk => 'Medium Boat (12 pax, 200 PK)',
        };
    }
}
