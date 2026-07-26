<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'name',
    'symbol',
    'exchange_rate_to_base',
    'effective_date',
    'is_active',
])]
class Currency extends Model
{
    protected function casts(): array
    {
        return [
            'exchange_rate_to_base' => 'decimal:4',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }
}
