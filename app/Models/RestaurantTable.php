<?php

namespace App\Models;

use App\Enums\RestaurantTableArea;
use App\Enums\RestaurantTableStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'name',
    'area',
    'capacity',
    'status',
])]
class RestaurantTable extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'area' => RestaurantTableArea::class,
            'status' => RestaurantTableStatus::class,
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
