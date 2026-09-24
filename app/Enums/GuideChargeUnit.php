<?php

namespace App\Enums;

enum GuideChargeUnit: string
{
    case PerDay = 'per_day';
    case HalfDay = 'half_day';
    case PerTrip = 'per_trip';

    public function label(): string
    {
        return match ($this) {
            self::PerDay => 'Per day',
            self::HalfDay => 'Half day',
            self::PerTrip => 'Per trip',
        };
    }

    public function descriptionPhrase(): string
    {
        return match ($this) {
            self::PerDay => 'per day',
            self::HalfDay => 'half day',
            self::PerTrip => 'per trip',
        };
    }

    public function buildDescription(string $rateItemName): string
    {
        $base = preg_replace('/\s*\(per day\)\s*$/iu', '', $rateItemName) ?? $rateItemName;
        $base = trim($base);

        return $base.' ('.$this->descriptionPhrase().')';
    }

    public function usesRateItemPrice(): bool
    {
        return $this === self::PerDay;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsPayload(): array
    {
        return array_map(
            fn (self $unit): array => [
                'value' => $unit->value,
                'label' => $unit->label(),
            ],
            self::cases(),
        );
    }
}
