<?php

namespace App\Enums;

enum BoatEngineOption: string
{
    case Hp200 = '200_hp';
    case Hp200x2 = '200_hp_x2';

    public function label(): string
    {
        return match ($this) {
            self::Hp200 => '200 HP',
            self::Hp200x2 => '200 HP x 2',
        };
    }
}
