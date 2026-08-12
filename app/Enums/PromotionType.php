<?php

namespace App\Enums;

enum PromotionType: string
{
    case Corporate = 'corporate';
    case EarlyBird = 'early_bird';
    case LastMinute = 'last_minute';
    case Seasonal = 'seasonal';
    case Package = 'package';

    public function label(): string
    {
        return match ($this) {
            self::Corporate => 'Corporate',
            self::EarlyBird => 'Early Bird',
            self::LastMinute => 'Last Minute',
            self::Seasonal => 'Seasonal',
            self::Package => 'Package',
        };
    }
}
