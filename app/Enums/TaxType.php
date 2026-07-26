<?php

namespace App\Enums;

enum TaxType: string
{
    case PpnOutput = 'ppn_output';
    case PpnInput = 'ppn_input';
    case Pph21 = 'pph21';
    case Pph23 = 'pph23';
    case Pph42 = 'pph4_2';
    case Pbb = 'pbb';

    public function label(): string
    {
        return match ($this) {
            self::PpnOutput => 'PPN Output',
            self::PpnInput => 'PPN Input',
            self::Pph21 => 'PPh 21',
            self::Pph23 => 'PPh 23',
            self::Pph42 => 'PPh 4(2)',
            self::Pbb => 'PBB',
        };
    }
}
