<?php

namespace App\Enums;

enum AgentRateCategory: string
{
    case A = 'A';
    case B = 'B';
    case C = 'C';
    case D = 'D';

    public function label(): string
    {
        return $this->value;
    }
}
