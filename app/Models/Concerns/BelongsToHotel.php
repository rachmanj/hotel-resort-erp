<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToHotel
{
    protected static function nullableHotelId(): bool
    {
        return false;
    }

    public static function bootBelongsToHotel(): void
    {
        static::addGlobalScope('hotel', function (Builder $builder): void {
            $hotelId = session('current_hotel_id');

            if ($hotelId === null) {
                return;
            }

            $table = $builder->getModel()->getTable();

            if (static::nullableHotelId()) {
                $builder->where(function (Builder $query) use ($hotelId, $table): void {
                    $query->where("{$table}.hotel_id", $hotelId)
                        ->orWhereNull("{$table}.hotel_id");
                });
            } else {
                $builder->where("{$table}.hotel_id", $hotelId);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('hotel_id') === null && session('current_hotel_id') !== null) {
                $model->setAttribute('hotel_id', session('current_hotel_id'));
            }
        });
    }
}
