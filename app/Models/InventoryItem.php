<?php

namespace App\Models;

use App\Enums\InventoryCategory;
use App\Enums\InventoryUnit;
use App\Models\Concerns\BelongsToHotel;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'name',
    'category',
    'unit',
    'current_stock',
    'reorder_level',
    'location_type',
    'location_id',
])]
class InventoryItem extends Model
{
    use BelongsToHotel, LogsActivity;

    protected static function nullableHotelId(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'category' => InventoryCategory::class,
            'unit' => InventoryUnit::class,
            'current_stock' => 'decimal:2',
            'reorder_level' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return (float) $this->current_stock <= (float) $this->reorder_level;
    }
}
