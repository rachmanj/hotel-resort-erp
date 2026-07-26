<?php

namespace App\Models;

use App\Enums\ArInvoiceStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'hotel_id',
    'invoice_no',
    'company_id',
    'period_start',
    'period_end',
    'total_amount',
    'paid_amount',
    'original_currency_code',
    'original_amount',
    'exchange_rate_id',
    'status',
    'due_date',
    'issued_at',
])]
class ArInvoice extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'original_amount' => 'decimal:2',
            'status' => ArInvoiceStatus::class,
            'due_date' => 'date',
            'issued_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }

    public function folios(): BelongsToMany
    {
        return $this->belongsToMany(Folio::class, 'ar_invoice_folios');
    }

    public function balanceDue(): float
    {
        return round((float) $this->total_amount - (float) $this->paid_amount, 2);
    }
}
