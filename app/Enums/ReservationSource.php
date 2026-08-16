<?php

namespace App\Enums;

enum ReservationSource: string
{
    case Walkin = 'walkin';
    case Phone = 'phone';
    case Ota = 'ota';
    case Telegram = 'telegram';
    case Web = 'web';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Walkin => 'Walk-in',
            self::Phone => 'Phone',
            self::Ota => 'OTA',
            self::Telegram => 'Telegram',
            self::Web => 'Web',
            self::Agent => 'Agent',
        };
    }
}
