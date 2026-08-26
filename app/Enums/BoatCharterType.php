<?php

namespace App\Enums;

enum BoatCharterType: string
{
    case Diving = 'diving';
    case Trip = 'trip';

    public function label(): string
    {
        return match ($this) {
            self::Diving => 'Diving',
            self::Trip => 'Trip',
        };
    }
}
