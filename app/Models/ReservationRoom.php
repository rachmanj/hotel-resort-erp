<?php

namespace App\Models;

use App\Enums\ReservationRoomStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reservation_id',
    'room_id',
    'room_type_id',
    'rate_plan_id',
    'nightly_rate',
    'check_in_at',
    'check_out_at',
    'status',
])]
class ReservationRoom extends Model
{
    protected function casts(): array
    {
        return [
            'nightly_rate' => 'decimal:2',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'status' => ReservationRoomStatus::class,
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }
}
