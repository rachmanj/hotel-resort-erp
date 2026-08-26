<?php

namespace App\Models;

use App\Enums\OtaFeeChargeStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hotel_id',
    'ota_fee_id',
    'reservation_id',
    'folio_id',
    'base_amount',
    'fee_pct',
    'fee_amount',
    'status',
    'earned_at',
])]
class OtaFeeCharge extends Model
{
    use BelongsToHotel;

    protected static function nullableHotelId(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'status' => OtaFeeChargeStatus::class,
            'base_amount' => 'decimal:2',
            'fee_pct' => 'decimal:2',
            'fee_amount' => 'decimal:2',
            'earned_at' => 'datetime',
        ];
    }

    public function otaFee(): BelongsTo
    {
        return $this->belongsTo(OtaFee::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }
}
