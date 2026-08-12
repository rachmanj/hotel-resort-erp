<?php

namespace App\Models;

use App\Enums\FolioStatus;
use App\Enums\FolioType;
use App\Models\Concerns\BelongsToHotel;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'folio_no',
    'reservation_id',
    'reservation_group_id',
    'guest_id',
    'company_id',
    'type',
    'status',
    'display_currency_code',
    'opened_at',
    'closed_at',
])]
class Folio extends Model
{
    use BelongsToHotel, LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => FolioType::class,
            'status' => FolioStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function reservationGroup(): BelongsTo
    {
        return $this->belongsTo(ReservationGroup::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FolioItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
