<?php

namespace App\Enums;

enum CommissionBasis: string
{
    case Gross = 'gross';
    case NetRoom = 'net_room';
    case NetRoomNoTax = 'net_room_no_tax';

    public function label(): string
    {
        return match ($this) {
            self::Gross => 'Gross (room + tax + SC)',
            self::NetRoom => 'Net Room (pre-tax)',
            self::NetRoomNoTax => 'Net Room (excl. tax lines)',
        };
    }
}
