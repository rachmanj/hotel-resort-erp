<?php

namespace App\Enums;

enum BudgetDepartment: string
{
    case Rooms = 'rooms';
    case Fb = 'fb';
    case Spa = 'spa';
    case Admin = 'admin';
    case Maintenance = 'maintenance';
    case Marketing = 'marketing';

    public function label(): string
    {
        return match ($this) {
            self::Rooms => 'Rooms',
            self::Fb => 'F&B',
            self::Spa => 'Spa',
            self::Admin => 'Administration',
            self::Maintenance => 'Maintenance',
            self::Marketing => 'Marketing',
        };
    }
}
