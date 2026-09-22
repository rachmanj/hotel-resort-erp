<?php

namespace App\Enums;

enum ProformaInvoiceStatus: string
{
    case Draft = 'draft';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Released => 'Released',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'default',
            self::Released => 'green',
        };
    }
}
