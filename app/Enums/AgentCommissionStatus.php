<?php

namespace App\Enums;

enum AgentCommissionStatus: string
{
    case Pending = 'pending';
    case Invoiced = 'invoiced';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Invoiced => 'Invoiced',
            self::Paid => 'Paid',
        };
    }
}
