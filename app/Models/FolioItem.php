<?php

namespace App\Models;

use App\Enums\FolioItemType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'folio_id',
    'revenue_category_id',
    'department_id',
    'item_type',
    'description',
    'reference_type',
    'reference_id',
    'quantity',
    'unit_price',
    'amount',
    'tax_amount',
    'service_charge_amount',
    'original_currency_code',
    'original_amount',
    'exchange_rate_id',
    'posted_by',
    'posted_at',
])]
class FolioItem extends Model
{
    protected function casts(): array
    {
        return [
            'item_type' => FolioItemType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function revenueCategory(): BelongsTo
    {
        return $this->belongsTo(RevenueCategory::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }

    public function getLineTotalAttribute(): float
    {
        return (float) $this->amount + (float) $this->tax_amount + (float) $this->service_charge_amount;
    }
}
