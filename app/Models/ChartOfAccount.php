<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'parent_id',
    'account_code',
    'name',
    'account_type',
    'normal_balance',
    'is_postable',
    'is_active',
])]
class ChartOfAccount extends Model
{
    use BelongsToHotel;

    protected static function nullableHotelId(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'normal_balance' => NormalBalance::class,
            'is_postable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function generalLedgerEntries(): HasMany
    {
        return $this->hasMany(GeneralLedger::class);
    }
}
