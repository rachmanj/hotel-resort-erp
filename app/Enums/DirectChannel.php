<?php

namespace App\Enums;

enum DirectChannel: string
{
    case Web = 'web';
    case Phone = 'phone';
    case SocialMedia = 'social_media';
    case Telegram = 'telegram';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Phone => 'Phone',
            self::SocialMedia => 'Social Media',
            self::Telegram => 'Telegram',
        };
    }
}
