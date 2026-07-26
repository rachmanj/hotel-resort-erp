<?php

namespace App\Enums;

enum SpaAppointmentStatus: string
{
    case Booked = 'booked';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::Confirmed => 'Confirmed',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow => 'No Show',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Booked => '#1677ff',
            self::Confirmed => '#13c2c2',
            self::InProgress => '#fa8c16',
            self::Completed => '#52c41a',
            self::Cancelled => '#ff4d4f',
            self::NoShow => '#8c8c8c',
        };
    }
}
