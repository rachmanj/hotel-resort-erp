<?php

namespace App\Models;

use App\Enums\ProformaPaymentMethod;
use App\Enums\ProformaPaymentStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hotel_id',
    'proforma_invoice_id',
    'reservation_id',
    'amount',
    'method',
    'received_from',
    'reference_no',
    'proof_path',
    'paid_at',
    'status',
    'recorded_by',
    'verified_by',
    'verified_at',
    'receipt_sequence',
    'receipt_number',
    'receipt_issued_at',
    'notes',
])]
class ProformaPayment extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => ProformaPaymentMethod::class,
            'status' => ProformaPaymentStatus::class,
            'paid_at' => 'date',
            'verified_at' => 'datetime',
            'receipt_sequence' => 'integer',
            'receipt_issued_at' => 'datetime',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isVerified(): bool
    {
        return $this->status === ProformaPaymentStatus::Verified;
    }
}
