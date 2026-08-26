<?php

namespace App\Enums;

enum BoatCharterStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Billed = 'billed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Billed => 'Billed',
            self::Cancelled => 'Cancelled',
        };
    }
}
