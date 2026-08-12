<?php

namespace App\Models;

use App\Enums\AgentRateDiscountType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agent_id',
    'room_type_id',
    'rate_plan_id',
    'nightly_rate',
    'discount_type',
    'discount_value',
    'valid_from',
    'valid_to',
    'is_active',
])]
class AgentRate extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'discount_type' => AgentRateDiscountType::class,
            'nightly_rate' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }
}
