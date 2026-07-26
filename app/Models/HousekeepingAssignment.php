<?php

namespace App\Models;

use App\Enums\HousekeepingAssignmentStatus;
use App\Enums\HousekeepingShift;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'room_id',
    'housekeeper_id',
    'assignment_date',
    'shift',
    'status',
    'assigned_by',
])]
class HousekeepingAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'assignment_date' => 'date',
            'shift' => HousekeepingShift::class,
            'status' => HousekeepingAssignmentStatus::class,
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function housekeeper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'housekeeper_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HousekeepingLog::class);
    }
}
