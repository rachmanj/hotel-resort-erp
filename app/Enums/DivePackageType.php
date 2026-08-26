<?php

namespace App\Enums;

enum DivePackageType: string
{
    case DivePackage = 'dive_package';
    case DiscoveryScuba = 'discovery_scuba';
    case NightDive = 'night_dive';

    public function label(): string
    {
        return match ($this) {
            self::DivePackage => 'Dive Package',
            self::DiscoveryScuba => 'Discovery Scuba Diving',
            self::NightDive => 'Night Dive',
        };
    }
}
