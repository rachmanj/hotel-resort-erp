<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'spa_therapist_id',
    'work_date',
    'start_time',
    'end_time',
])]
class SpaTherapistSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function therapist(): BelongsTo
    {
        return $this->belongsTo(SpaTherapist::class, 'spa_therapist_id');
    }
}
