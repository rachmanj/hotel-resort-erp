<?php

namespace App\Support;

use App\Enums\FolioItemType;

class FolioItemAppliesTo
{
    public const MISC = 'all';

    public static function forItemType(string $itemType): string
    {
        return match ($itemType) {
            FolioItemType::Room->value, 'room' => 'room',
            FolioItemType::Fb->value, 'fb' => 'fb',
            FolioItemType::Spa->value, 'spa' => 'spa',
            FolioItemType::Misc->value, 'misc' => self::MISC,
            default => self::MISC,
        };
    }
}
