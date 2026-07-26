<?php

namespace App\Models;

use App\Enums\AccountingPeriodStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'name',
    'start_date',
    'end_date',
    'status',
    'closed_at',
    'closed_by',
])]
class AccountingPeriod extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => AccountingPeriodStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function generalLedgerEntries(): HasMany
    {
        return $this->hasMany(GeneralLedger::class);
    }

    public function isOpen(): bool
    {
        return $this->status === AccountingPeriodStatus::Open;
    }
}
