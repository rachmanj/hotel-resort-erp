<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'dine_in';
    case RoomService = 'room_service';
    case Takeaway = 'takeaway';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine In',
            self::RoomService => 'Room Service',
            self::Takeaway => 'Takeaway',
        };
    }
}
