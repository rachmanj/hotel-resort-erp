<?php

namespace App\Models;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceReportedVia;
use App\Enums\MaintenanceRequestStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'room_id',
    'asset_id',
    'reported_by',
    'reported_via',
    'priority',
    'description',
    'status',
    'assigned_to',
    'resolved_at',
])]
class MaintenanceRequest extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'reported_via' => MaintenanceReportedVia::class,
            'priority' => MaintenancePriority::class,
            'status' => MaintenanceRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
