<?php

namespace App\Models;

use App\Enums\ProformaInvoiceStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'reservation_id',
    'sequence',
    'year',
    'number',
    'status',
    'revision',
    'issued_at',
    'released_at',
    'released_by',
    'subtotal',
    'total',
    'received_total',
    'outstanding_total',
    'prepared_by',
    'notes',
])]
class ProformaInvoice extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'year' => 'integer',
            'revision' => 'integer',
            'status' => ProformaInvoiceStatus::class,
            'issued_at' => 'datetime',
            'released_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'received_total' => 'decimal:2',
            'outstanding_total' => 'decimal:2',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProformaInvoiceLine::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ProformaPayment::class)->orderBy('paid_at')->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === ProformaInvoiceStatus::Draft;
    }

    public function isReleased(): bool
    {
        return $this->status === ProformaInvoiceStatus::Released;
    }
}
