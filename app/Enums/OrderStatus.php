<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Preparing => 'Preparing',
            self::Ready => 'Ready',
            self::Served => 'Served',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => '#1677ff',
            self::Preparing => '#fa8c16',
            self::Ready => '#52c41a',
            self::Served => '#8c8c8c',
            self::Cancelled => '#ff4d4f',
        };
    }
}
