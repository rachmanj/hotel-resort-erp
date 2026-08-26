<?php

namespace App\Enums;

enum GuideType: string
{
    case Employee = 'employee';
    case Freelance = 'freelance';

    public function label(): string
    {
        return match ($this) {
            self::Employee => 'Employee',
            self::Freelance => 'Freelance',
        };
    }
}
