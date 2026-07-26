<?php

namespace App\Models;

use App\Enums\GuestIncidentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'guest_id',
    'reservation_id',
    'type',
    'description',
    'reported_by',
    'occurred_at',
])]
class GuestIncident extends Model
{
    protected function casts(): array
    {
        return [
            'type' => GuestIncidentType::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
