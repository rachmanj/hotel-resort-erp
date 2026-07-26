<?php

namespace App\Models;

use App\Enums\HousekeepingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'room_id',
    'housekeeping_assignment_id',
    'status',
    'changed_by',
    'changed_via',
    'notes',
    'changed_at',
])]
class HousekeepingLog extends Model
{
    protected function casts(): array
    {
        return [
            'status' => HousekeepingStatus::class,
            'changed_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(HousekeepingAssignment::class, 'housekeeping_assignment_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
