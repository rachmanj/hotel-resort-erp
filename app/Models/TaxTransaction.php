<?php

namespace App\Models;

use App\Enums\TaxTransactionStatus;
use App\Enums\TaxType;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'hotel_id',
    'tax_type',
    'source_type',
    'source_id',
    'transaction_date',
    'base_amount',
    'tax_rate_percent',
    'tax_amount',
    'tax_period',
    'status',
])]
class TaxTransaction extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'tax_type' => TaxType::class,
            'transaction_date' => 'date',
            'base_amount' => 'decimal:2',
            'tax_rate_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'status' => TaxTransactionStatus::class,
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
