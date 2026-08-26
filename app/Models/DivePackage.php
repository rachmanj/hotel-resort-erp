<?php

namespace App\Models;

use App\Enums\DivePackageType;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'code',
    'name',
    'type',
    'price_per_person',
    'min_pax',
    'includes',
    'is_active',
])]
class DivePackage extends Model
{
    use BelongsToHotel;

    protected static function nullableHotelId(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'type' => DivePackageType::class,
            'price_per_person' => 'decimal:2',
            'min_pax' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function boatCharters(): HasMany
    {
        return $this->hasMany(BoatCharter::class);
    }
}
