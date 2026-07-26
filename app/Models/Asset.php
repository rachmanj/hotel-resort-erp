<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\DepreciationMethod;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'name',
    'asset_code',
    'asset_type',
    'room_id',
    'location',
    'purchased_at',
    'acquisition_date',
    'acquisition_cost',
    'residual_value',
    'useful_life_years',
    'depreciation_method',
    'accumulated_depreciation',
    'net_book_value',
    'last_depreciation_date',
    'chart_of_account_id',
    'accumulated_depreciation_account_id',
    'warranty_until',
    'status',
])]
class Asset extends Model
{
    use BelongsToHotel;

    protected static function nullableHotelId(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'asset_type' => AssetType::class,
            'status' => AssetStatus::class,
            'purchased_at' => 'date',
            'acquisition_date' => 'date',
            'acquisition_cost' => 'decimal:2',
            'residual_value' => 'decimal:2',
            'useful_life_years' => 'integer',
            'depreciation_method' => DepreciationMethod::class,
            'accumulated_depreciation' => 'decimal:2',
            'net_book_value' => 'decimal:2',
            'last_depreciation_date' => 'date',
            'warranty_until' => 'date',
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

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'accumulated_depreciation_account_id');
    }

    public function isDepreciable(): bool
    {
        return $this->acquisition_cost !== null
            && (float) $this->acquisition_cost > 0
            && $this->useful_life_years !== null
            && $this->useful_life_years > 0
            && $this->depreciation_method !== null
            && $this->chart_of_account_id !== null
            && $this->accumulated_depreciation_account_id !== null;
    }
}
