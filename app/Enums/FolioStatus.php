<?php

namespace App\Enums;

enum FolioStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Voided => 'Voided',
        };
    }
}
