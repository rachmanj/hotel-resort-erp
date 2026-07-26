<?php

namespace App\Enums;

enum GuestIncidentType: string
{
    case Damage = 'damage';
    case NoShow = 'noshow';
    case Misconduct = 'misconduct';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Damage => 'Damage',
            self::NoShow => 'No Show',
            self::Misconduct => 'Misconduct',
            self::Other => 'Other',
        };
    }
}
