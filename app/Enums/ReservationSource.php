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
    case TravelAgent = 'travel_agent';
    case Corporate = 'corporate';
    case Direct = 'direct';

    public function label(): string
    {
        return match ($this) {
            self::Walkin => 'Walk-in',
            self::Phone => 'Phone',
            self::Ota => 'OTA',
            self::Telegram => 'Telegram',
            self::Web => 'Web',
            self::Agent => 'Agent',
            self::TravelAgent => 'Travel Agent',
            self::Corporate => 'Corporate',
            self::Direct => 'Direct',
        };
    }

    public function isLegacy(): bool
    {
        return in_array($this, [
            self::Phone,
            self::Web,
            self::Telegram,
            self::Agent,
        ], true);
    }

    public function isCategorised(): bool
    {
        return in_array($this, self::categorised(), true);
    }

    /**
     * @return list<self>
     */
    public static function categorised(): array
    {
        return [
            self::Ota,
            self::TravelAgent,
            self::Corporate,
            self::Direct,
            self::Walkin,
        ];
    }

    /**
     * @return array<string, array{source: string, direct_channel: string|null}>
     */
    public static function legacyMapping(): array
    {
        return [
            'agent' => ['source' => self::TravelAgent->value, 'direct_channel' => null],
            'phone' => ['source' => self::Direct->value, 'direct_channel' => DirectChannel::Phone->value],
            'web' => ['source' => self::Direct->value, 'direct_channel' => DirectChannel::Web->value],
            'telegram' => ['source' => self::Direct->value, 'direct_channel' => DirectChannel::Telegram->value],
            'walkin' => ['source' => self::Walkin->value, 'direct_channel' => null],
            'ota' => ['source' => self::Ota->value, 'direct_channel' => null],
        ];
    }

    /**
     * @return list<string>
     */
    public static function categorisedValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::categorised());
    }
}
