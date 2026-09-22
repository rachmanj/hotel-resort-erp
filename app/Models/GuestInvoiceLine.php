<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'guest_invoice_id',
    'folio_item_id',
    'sort_order',
    'description',
    'quantity',
    'nights',
    'unit_price',
    'amount',
    'tax_amount',
    'service_charge_amount',
    'line_total',
])]
class GuestInvoiceLine extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'quantity' => 'decimal:2',
            'nights' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function guestInvoice(): BelongsTo
    {
        return $this->belongsTo(GuestInvoice::class);
    }

    public function folioItem(): BelongsTo
    {
        return $this->belongsTo(FolioItem::class);
    }
}
