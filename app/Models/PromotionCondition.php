<?php

namespace App\Models;

use App\Enums\PromotionConditionType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'promotion_id',
    'condition_type',
    'value',
])]
class PromotionCondition extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'condition_type' => PromotionConditionType::class,
            'value' => 'array',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
