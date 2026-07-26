<?php

namespace App\Models;

use App\Enums\RoomStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'hotel_id',
    'room_type_id',
    'floor_id',
    'number',
    'status',
    'notes',
])]
class Room extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(Floor::class);
    }

    public function housekeepingLogs(): HasMany
    {
        return $this->hasMany(HousekeepingLog::class);
    }

    public function latestHousekeepingLog(): HasOne
    {
        return $this->hasOne(HousekeepingLog::class)->latestOfMany('changed_at');
    }

    public function housekeepingAssignments(): HasMany
    {
        return $this->hasMany(HousekeepingAssignment::class);
    }
}
