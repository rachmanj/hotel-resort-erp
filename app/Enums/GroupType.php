<?php

namespace App\Enums;

enum GroupType: string
{
    case SingleMultiRoom = 'single_multi_room';
    case Linked = 'linked';
    case CorporateEvent = 'corporate_event';

    public function label(): string
    {
        return match ($this) {
            self::SingleMultiRoom => 'Single Reservation (Multi-Room)',
            self::Linked => 'Linked Reservations',
            self::CorporateEvent => 'Corporate / Event',
        };
    }
}
