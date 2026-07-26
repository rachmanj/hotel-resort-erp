<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_order_id',
    'inventory_item_id',
    'quantity_ordered',
    'unit_cost',
    'quantity_received',
])]
class PurchaseOrderItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity_ordered' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'quantity_received' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function isFullyReceived(): bool
    {
        return (float) $this->quantity_received >= (float) $this->quantity_ordered;
    }
}
