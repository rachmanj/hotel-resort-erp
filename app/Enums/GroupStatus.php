<?php

namespace App\Enums;

enum GroupStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case PartiallyCheckedIn = 'partially_checked_in';
    case CheckedIn = 'checked_in';
    case PartiallyCheckedOut = 'partially_checked_out';
    case CheckedOut = 'checked_out';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Confirmed => 'Confirmed',
            self::PartiallyCheckedIn => 'Partially Checked In',
            self::CheckedIn => 'Checked In',
            self::PartiallyCheckedOut => 'Partially Checked Out',
            self::CheckedOut => 'Checked Out',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'default',
            self::Confirmed => 'blue',
            self::PartiallyCheckedIn => 'cyan',
            self::CheckedIn => 'green',
            self::PartiallyCheckedOut => 'orange',
            self::CheckedOut => 'default',
            self::Cancelled => 'red',
        };
    }
}
