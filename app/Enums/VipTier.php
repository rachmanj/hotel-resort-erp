<?php

namespace App\Enums;

enum VipTier: string
{
    case None = 'none';
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Silver => 'Silver',
            self::Gold => 'Gold',
            self::Platinum => 'Platinum',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'default',
            self::Silver => 'default',
            self::Gold => 'gold',
            self::Platinum => 'purple',
        };
    }
}
