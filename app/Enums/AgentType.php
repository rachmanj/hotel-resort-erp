<?php

namespace App\Enums;

enum AgentType: string
{
    case Ota = 'ota';
    case Travel = 'travel';
    case Corporate = 'corporate';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Ota => 'OTA',
            self::Travel => 'Travel Agent',
            self::Corporate => 'Corporate',
            self::Internal => 'Internal Sales',
        };
    }
}
