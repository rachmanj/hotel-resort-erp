<?php

namespace App\Enums;

enum ReservationRoomStatus: string
{
    case Booked = 'booked';
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Booked',
            self::CheckedIn => 'Checked In',
            self::CheckedOut => 'Checked Out',
            self::NoShow => 'No Show',
            self::Cancelled => 'Cancelled',
        };
    }
}
