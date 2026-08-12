<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'maintenance_request_id',
    'asset_id',
    'assigned_to',
    'description',
    'status',
    'completed_at',
    'cost',
])]
class WorkOrder extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'completed_at' => 'datetime',
            'cost' => 'decimal:2',
        ];
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
