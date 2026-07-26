<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'user_id',
    'name',
    'phone',
])]
class SpaTherapist extends Model
{
    use BelongsToHotel;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SpaTherapistSchedule::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(SpaAppointment::class);
    }
}
