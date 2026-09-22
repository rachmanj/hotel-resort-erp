<?php

namespace App\Models;

use App\Enums\GuestInvoiceStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'folio_id',
    'reservation_id',
    'sequence',
    'year',
    'month',
    'number',
    'status',
    'revision',
    'prepared_by',
    'approved_by',
    'issued_at',
    'released_at',
    'released_by',
    'subtotal',
    'total',
    'notes',
])]
class GuestInvoice extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'year' => 'integer',
            'month' => 'integer',
            'revision' => 'integer',
            'status' => GuestInvoiceStatus::class,
            'issued_at' => 'datetime',
            'released_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GuestInvoiceLine::class)->orderBy('sort_order');
    }

    public function isDraft(): bool
    {
        return $this->status === GuestInvoiceStatus::Draft;
    }

    public function isReleased(): bool
    {
        return $this->status === GuestInvoiceStatus::Released;
    }
}
