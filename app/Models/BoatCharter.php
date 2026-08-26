<?php

namespace App\Models;

use App\Enums\BoatCharterStatus;
use App\Enums\BoatCharterType;
use App\Enums\GuideType;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hotel_id',
    'boat_unit_id',
    'dive_package_id',
    'reservation_id',
    'folio_id',
    'folio_item_id',
    'trip_date',
    'destination',
    'charter_type',
    'price',
    'quantity',
    'bbm_liters',
    'bbm_cost',
    'guide_type',
    'guide_fee',
    'status',
    'notes',
])]
class BoatCharter extends Model
{
    use BelongsToHotel;

    protected static function nullableHotelId(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'charter_type' => BoatCharterType::class,
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'bbm_liters' => 'decimal:2',
            'bbm_cost' => 'decimal:2',
            'guide_type' => GuideType::class,
            'guide_fee' => 'decimal:2',
            'status' => BoatCharterStatus::class,
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function boatUnit(): BelongsTo
    {
        return $this->belongsTo(BoatUnit::class);
    }

    public function divePackage(): BelongsTo
    {
        return $this->belongsTo(DivePackage::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function folioItem(): BelongsTo
    {
        return $this->belongsTo(FolioItem::class);
    }
}
