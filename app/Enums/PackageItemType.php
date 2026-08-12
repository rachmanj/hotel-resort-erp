<?php

namespace App\Enums;

enum PackageItemType: string
{
    case FbMenuItem = 'fb_menu_item';
    case SpaTreatment = 'spa_treatment';

    public function label(): string
    {
        return match ($this) {
            self::FbMenuItem => 'F&B Menu Item',
            self::SpaTreatment => 'Spa Treatment',
        };
    }
}
