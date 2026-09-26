<?php

namespace App\Enums;

enum BankMatchType: string
{
    case AutoReference = 'auto_reference';
    case AutoExact = 'auto_exact';
    case AutoFuzzy = 'auto_fuzzy';
    case AutoSplit = 'auto_split';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::AutoReference => 'Auto (Reference)',
            self::AutoExact => 'Auto (Exact)',
            self::AutoFuzzy => 'Auto (Fuzzy)',
            self::AutoSplit => 'Auto (Split)',
            self::Manual => 'Manual',
        };
    }

    public function isAutomatic(): bool
    {
        return $this !== self::Manual;
    }
}
