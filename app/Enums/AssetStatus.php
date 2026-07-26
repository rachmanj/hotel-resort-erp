<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Operational = 'operational';
    case UnderMaintenance = 'under_maintenance';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operational',
            self::UnderMaintenance => 'Under Maintenance',
            self::Retired => 'Retired',
        };
    }
}
