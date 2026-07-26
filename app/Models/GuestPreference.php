<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'guest_id',
    'key',
    'value',
    'notes',
])]
class GuestPreference extends Model
{
    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }
}
