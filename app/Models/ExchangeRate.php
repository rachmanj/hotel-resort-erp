<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'currency_id',
    'rate_to_base',
    'effective_date',
])]
class ExchangeRate extends Model
{
    protected function casts(): array
    {
        return [
            'rate_to_base' => 'decimal:4',
            'effective_date' => 'date',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
