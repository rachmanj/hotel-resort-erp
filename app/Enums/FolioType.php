<?php

namespace App\Enums;

enum FolioType: string
{
    case Master = 'master';
    case Incidental = 'incidental';
    case GroupDeposit = 'group_deposit';
    case GroupConsolidated = 'group_consolidated';

    public function label(): string
    {
        return match ($this) {
            self::Master => 'Master',
            self::Incidental => 'Incidental',
            self::GroupDeposit => 'Group Deposit',
            self::GroupConsolidated => 'Group Consolidated',
        };
    }
}
