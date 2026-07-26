<?php

namespace App\Enums;

enum FolioType: string
{
    case Master = 'master';
    case Incidental = 'incidental';

    public function label(): string
    {
        return match ($this) {
            self::Master => 'Master',
            self::Incidental => 'Incidental',
        };
    }
}
