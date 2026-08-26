<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'code',
    'name',
    'coa_account_code',
    'sort_order',
    'is_active',
])]
class RevenueCategory extends Model
{
    use BelongsToHotel;

    protected static function nullableHotelId(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function folioItems(): HasMany
    {
        return $this->hasMany(FolioItem::class);
    }
}
