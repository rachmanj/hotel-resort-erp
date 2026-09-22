<?php

namespace App\Enums;

enum ProformaPaymentStatus: string
{
    case Recorded = 'recorded';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Recorded',
            self::Verified => 'Verified',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Recorded => 'orange',
            self::Verified => 'green',
        };
    }
}
