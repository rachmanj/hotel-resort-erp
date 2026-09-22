<?php

namespace App\Enums;

enum DiveRateItemType: string
{
    case DivePackage = 'dive_package';
    case BoatRent = 'boat_rent';
    case NightDive = 'night_dive';
    case DiscoveryScuba = 'discovery_scuba';
    case EquipmentRental = 'equipment_rental';

    public function divePackageType(): ?DivePackageType
    {
        return match ($this) {
            self::DivePackage => DivePackageType::DivePackage,
            self::NightDive => DivePackageType::NightDive,
            self::DiscoveryScuba => DivePackageType::DiscoveryScuba,
            default => null,
        };
    }
}
