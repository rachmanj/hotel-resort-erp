<?php

namespace App\Enums;

enum InventoryCategory: string
{
    case Linen = 'linen';
    case Amenity = 'amenity';
    case FbIngredient = 'fb_ingredient';
    case SparePart = 'spare_part';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Linen => 'Linen',
            self::Amenity => 'Amenity',
            self::FbIngredient => 'F&B Ingredient',
            self::SparePart => 'Spare Part',
            self::Other => 'Other',
        };
    }
}
