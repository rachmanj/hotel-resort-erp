<?php

namespace App\Models;

use App\Enums\BoatEngineOption;
use App\Enums\DiveRateItemType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'name',
    'item_type',
    'route',
    'dive_spots',
    'boat_engine_option',
    'price',
    'min_pax',
    'valid_from',
    'valid_to',
])]
class DiveRateItem extends Model
{
    protected function casts(): array
    {
        return [
            'item_type' => DiveRateItemType::class,
            'boat_engine_option' => BoatEngineOption::class,
            'price' => 'decimal:2',
            'min_pax' => 'integer',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    /**
     * @return list<array{
     *     route: string,
     *     dive_spots: string,
     *     label: string,
     *     boat_prices: list<array{
     *         id: int,
     *         boat_engine_option: string,
     *         boat_engine_label: string,
     *         price: float
     *     }>
     * }>
     */
    public static function boatRoutesPayload(): array
    {
        /** @var Collection<int, DiveRateItem> $items */
        $items = self::query()
            ->where('item_type', DiveRateItemType::BoatRent)
            ->orderBy('route')
            ->orderBy('boat_engine_option')
            ->get();

        return $items
            ->groupBy(fn (DiveRateItem $item): string => $item->route.'|'.$item->dive_spots)
            ->map(function (Collection $rates): array {
                $first = $rates->first();

                return [
                    'route' => (string) $first->route,
                    'dive_spots' => (string) $first->dive_spots,
                    'label' => $first->route.' — '.$first->dive_spots,
                    'boat_prices' => $rates
                        ->map(fn (DiveRateItem $rate): array => [
                            'id' => $rate->id,
                            'boat_engine_option' => $rate->boat_engine_option->value,
                            'boat_engine_label' => $rate->boat_engine_option->label(),
                            'price' => (float) $rate->price,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }
}
