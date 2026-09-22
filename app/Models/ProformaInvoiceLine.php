<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'proforma_invoice_id',
    'sort_order',
    'description',
    'note',
    'quantity',
    'nights',
    'unit_price',
    'amount',
])]
class ProformaInvoiceLine extends Model
{
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'quantity' => 'integer',
            'nights' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    /**
     * Price column on the printed document: the nightly rate for one room, one night.
     */
    public function stayPrice(): float
    {
        return round((float) $this->unit_price, 2);
    }
}
