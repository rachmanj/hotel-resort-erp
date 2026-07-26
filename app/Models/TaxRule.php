<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'code',
    'rate_percent',
    'applies_to',
    'is_compounding',
    'is_active',
    'order',
])]
class TaxRule extends Model
{
    protected function casts(): array
    {
        return [
            'rate_percent' => 'decimal:2',
            'is_compounding' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
