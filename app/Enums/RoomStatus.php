<?php

namespace App\Enums;

enum RoomStatus: string
{
    case VacantClean = 'vacant_clean';
    case VacantDirty = 'vacant_dirty';
    case OccupiedClean = 'occupied_clean';
    case OccupiedDirty = 'occupied_dirty';
    case OutOfOrder = 'out_of_order';
    case OutOfService = 'out_of_service';
    case Reserved = 'reserved';

    public function label(): string
    {
        return match ($this) {
            self::VacantClean => 'Vacant Clean',
            self::VacantDirty => 'Vacant Dirty',
            self::OccupiedClean => 'Occupied Clean',
            self::OccupiedDirty => 'Occupied Dirty',
            self::OutOfOrder => 'Out of Order',
            self::OutOfService => 'Out of Service',
            self::Reserved => 'Reserved',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::VacantClean => 'green',
            self::VacantDirty => 'orange',
            self::OccupiedClean => 'blue',
            self::OccupiedDirty => 'volcano',
            self::OutOfOrder => 'red',
            self::OutOfService => 'magenta',
            self::Reserved => 'purple',
        };
    }
}
