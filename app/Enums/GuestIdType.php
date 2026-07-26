<?php

namespace App\Enums;

enum GuestIdType: string
{
    case Ktp = 'ktp';
    case Passport = 'passport';
    case Sim = 'sim';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Ktp => 'KTP',
            self::Passport => 'Passport',
            self::Sim => 'SIM',
            self::Other => 'Other',
        };
    }
}
