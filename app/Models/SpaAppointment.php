<?php

namespace App\Models;

use App\Enums\SpaAppointmentStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hotel_id',
    'spa_treatment_id',
    'spa_therapist_id',
    'guest_id',
    'reservation_id',
    'scheduled_at',
    'status',
    'charged_to_room',
    'folio_item_id',
])]
class SpaAppointment extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'status' => SpaAppointmentStatus::class,
            'charged_to_room' => 'boolean',
        ];
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(SpaTreatment::class, 'spa_treatment_id');
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(SpaTherapist::class, 'spa_therapist_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function folioItem(): BelongsTo
    {
        return $this->belongsTo(FolioItem::class);
    }
}
