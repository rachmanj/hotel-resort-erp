<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'purchase_requisition_id',
    'inventory_item_id',
    'quantity_requested',
])]
class PurchaseRequisitionItem extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'decimal:2',
        ];
    }

    public function purchaseRequisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
