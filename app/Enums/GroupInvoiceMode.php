<?php

namespace App\Enums;

enum GroupInvoiceMode: string
{
    case PerRoom = 'per_room';
    case Consolidated = 'consolidated';
    case Split = 'split';

    public function label(): string
    {
        return match ($this) {
            self::PerRoom => 'Per Room',
            self::Consolidated => 'Consolidated',
            self::Split => 'Split',
        };
    }
}
