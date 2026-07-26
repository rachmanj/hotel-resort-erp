<?php

namespace App\Enums;

enum RatePlanType: string
{
    case Standard = 'standard';
    case Weekend = 'weekend';
    case Seasonal = 'seasonal';
    case Promo = 'promo';
    case Corporate = 'corporate';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard',
            self::Weekend => 'Weekend',
            self::Seasonal => 'Seasonal',
            self::Promo => 'Promo',
            self::Corporate => 'Corporate',
        };
    }
}
