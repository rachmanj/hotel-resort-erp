<?php

namespace App\Models;

use App\Enums\GuestIdType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'full_name',
    'id_number',
    'id_type',
    'phone',
    'email',
    'address',
    'nationality',
])]
class Guest extends Model
{
    protected function casts(): array
    {
        return [
            'id_type' => GuestIdType::class,
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
