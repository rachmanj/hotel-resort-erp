<?php

namespace App\Enums;

enum MaintenanceReportedVia: string
{
    case Web = 'web';
    case Telegram = 'telegram';
    case Phone = 'phone';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Telegram => 'Telegram',
            self::Phone => 'Phone',
            self::Email => 'Email',
        };
    }
}
