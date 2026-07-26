<?php

namespace App\Enums;

enum CreatedVia: string
{
    case Web = 'web';
    case Telegram = 'telegram';
    case OtaWebhook = 'ota_webhook';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Telegram => 'Telegram',
            self::OtaWebhook => 'OTA Webhook',
        };
    }
}
