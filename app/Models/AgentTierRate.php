<?php

namespace App\Models;

use App\Enums\AgentRateCategory;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rate_category',
    'room_type_id',
    'nightly_rate',
    'valid_from',
    'valid_to',
    'is_active',
])]
class AgentTierRate extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'rate_category' => AgentRateCategory::class,
            'nightly_rate' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }
}
