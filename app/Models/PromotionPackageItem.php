<?php

namespace App\Models;

use App\Enums\PackageItemType;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'promotion_id',
    'item_type',
    'reference_id',
    'quantity',
    'package_value',
])]
class PromotionPackageItem extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'item_type' => PackageItemType::class,
            'package_value' => 'decimal:2',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
