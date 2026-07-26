<?php

namespace App\Enums;

enum HousekeepingStatus: string
{
    case Dirty = 'dirty';
    case Cleaning = 'cleaning';
    case Clean = 'clean';
    case Inspected = 'inspected';
    case Ready = 'ready';
    case OutOfOrder = 'out_of_order';

    public function label(): string
    {
        return match ($this) {
            self::Dirty => 'Dirty',
            self::Cleaning => 'Cleaning',
            self::Clean => 'Clean',
            self::Inspected => 'Inspected',
            self::Ready => 'Ready',
            self::OutOfOrder => 'Out of Order',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Dirty => '🔴',
            self::Cleaning => '🟡',
            self::Clean => '🟢',
            self::Inspected => '🔵',
            self::Ready => '✅',
            self::OutOfOrder => '⚫',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Dirty => 'red',
            self::Cleaning => 'orange',
            self::Clean => 'lime',
            self::Inspected => 'blue',
            self::Ready => 'green',
            self::OutOfOrder => 'default',
        };
    }

    public static function fromRoomStatus(RoomStatus $roomStatus): self
    {
        return match ($roomStatus) {
            RoomStatus::VacantDirty, RoomStatus::OccupiedDirty => self::Dirty,
            RoomStatus::OccupiedClean => self::Clean,
            RoomStatus::VacantClean => self::Ready,
            RoomStatus::OutOfOrder, RoomStatus::OutOfService => self::OutOfOrder,
            RoomStatus::Reserved => self::Ready,
        };
    }
}
