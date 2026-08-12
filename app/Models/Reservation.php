<?php

namespace App\Models;

use App\Enums\CreatedVia;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Concerns\BelongsToHotel;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'reservation_code',
    'external_booking_id',
    'guest_id',
    'agent_id',
    'reservation_group_id',
    'promotion_id',
    'source',
    'status',
    'arrival_date',
    'departure_date',
    'adults',
    'children',
    'special_requests',
    'created_by',
    'created_via',
    'cancelled_reason',
])]
class Reservation extends Model
{
    use BelongsToHotel, LogsActivity;

    protected function casts(): array
    {
        return [
            'source' => ReservationSource::class,
            'status' => ReservationStatus::class,
            'arrival_date' => 'date',
            'departure_date' => 'date',
            'created_via' => CreatedVia::class,
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function reservationGroup(): BelongsTo
    {
        return $this->belongsTo(ReservationGroup::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function promotionRedemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function reservationRooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }
}
