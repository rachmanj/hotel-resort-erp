<?php

namespace App\Enums;

enum AssetType: string
{
    case Hvac = 'hvac';
    case Plumbing = 'plumbing';
    case Electrical = 'electrical';
    case Furniture = 'furniture';
    case Appliance = 'appliance';
    case Machinery = 'machinery';
    case Equipment = 'equipment';
    case OfficeEquipment = 'office_equipment';
    case OfficeMachinery = 'office_machinery';
    case Housekeeping = 'housekeeping';
    case KitchenSet = 'kitchen_set';
    case Building = 'building';
    case Ship = 'ship';
    case Vehicle = 'vehicle';
    case OtherInventory = 'other_inventory';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Hvac => 'HVAC',
            self::Plumbing => 'Plumbing',
            self::Electrical => 'Electrical',
            self::Furniture => 'Furniture',
            self::Appliance => 'Appliance',
            self::Machinery => 'Machinery',
            self::Equipment => 'Equipment',
            self::OfficeEquipment => 'Office Equipment',
            self::OfficeMachinery => 'Office Machinery',
            self::Housekeeping => 'House Keeping',
            self::KitchenSet => 'Kitchen Set',
            self::Building => 'Building',
            self::Ship => 'Ship',
            self::Vehicle => 'Vehicle',
            self::OtherInventory => 'Other Inventory',
            self::Other => 'Other',
        };
    }
}
