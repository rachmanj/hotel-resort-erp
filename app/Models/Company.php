<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'tax_id',
    'billing_address',
    'phone',
    'email',
    'credit_limit',
    'payment_terms_days',
    'is_active',
])]
class Company extends Model
{
    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }
}
