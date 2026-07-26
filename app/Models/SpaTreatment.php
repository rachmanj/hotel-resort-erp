<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'name',
    'duration_minutes',
    'price',
    'description',
])]
class SpaTreatment extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(SpaAppointment::class);
    }
}
