<?php

namespace App\Enums;

enum InventoryUnit: string
{
    case Pcs = 'pcs';
    case Kg = 'kg';
    case Ltr = 'ltr';
    case Box = 'box';

    public function label(): string
    {
        return match ($this) {
            self::Pcs => 'Pieces',
            self::Kg => 'Kilogram',
            self::Ltr => 'Liter',
            self::Box => 'Box',
        };
    }
}
