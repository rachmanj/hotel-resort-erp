<?php

namespace App\Models;

use App\Enums\GroupInvoiceMode;
use App\Enums\GroupStatus;
use App\Enums\GroupType;
use App\Models\Concerns\BelongsToHotel;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'group_code',
    'group_type',
    'name',
    'pic_guest_id',
    'company_id',
    'invoice_mode',
    'arrival_date',
    'departure_date',
    'deposit_amount',
    'deposit_paid_at',
    'status',
    'special_requests',
    'created_by',
])]
class ReservationGroup extends Model
{
    use BelongsToHotel, LogsActivity;

    protected function casts(): array
    {
        return [
            'group_type' => GroupType::class,
            'invoice_mode' => GroupInvoiceMode::class,
            'status' => GroupStatus::class,
            'arrival_date' => 'date',
            'departure_date' => 'date',
            'deposit_amount' => 'decimal:2',
            'deposit_paid_at' => 'datetime',
        ];
    }

    public function picGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'pic_guest_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }

    public static function generateGroupCode(): string
    {
        $datePrefix = now()->format('Ymd');
        $prefix = "GRP-{$datePrefix}-";

        $lastCode = static::query()
            ->withoutGlobalScope('hotel')
            ->where('group_code', 'like', $prefix.'%')
            ->orderByDesc('group_code')
            ->value('group_code');

        $sequence = 1;
        if ($lastCode !== null) {
            $sequence = (int) substr($lastCode, -4) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
