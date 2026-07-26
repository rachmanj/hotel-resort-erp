<?php

namespace App\Enums;

enum AssetType: string
{
    case Hvac = 'hvac';
    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case Furniture = 'furniture';
    case Appliance = 'appliance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Hvac => 'HVAC',
            self::Plumbing => 'Plumbing',
            self::Electrical => 'Electrical',
            self::Furniture => 'Furniture',
            self::Appliance => 'Appliance',
            self::Other => 'Other',
        };
    }
}
