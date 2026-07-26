<?php

namespace App\Models;

use App\Enums\SupplierInvoiceStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'invoice_no',
    'supplier_id',
    'purchase_order_id',
    'invoice_date',
    'due_date',
    'subtotal',
    'tax_amount',
    'withholding_tax_amount',
    'total_amount',
    'status',
    'paid_at',
])]
class SupplierInvoice extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'withholding_tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => SupplierInvoiceStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class);
    }
}
