<?php

namespace App\Models;

use App\Enums\OtaFeeType;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'code',
    'name',
    'fee_type',
    'base_fee_pct',
    'variable_fee_pct',
    'flat_fee_per_room_night',
    'is_active',
])]
class OtaFee extends Model
{
    use BelongsToHotel;

    protected static function nullableHotelId(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'fee_type' => OtaFeeType::class,
            'base_fee_pct' => 'decimal:2',
            'variable_fee_pct' => 'decimal:2',
            'flat_fee_per_room_night' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(OtaFeeCharge::class);
    }
}
