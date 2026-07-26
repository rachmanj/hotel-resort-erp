<?php

namespace App\Models;

use App\Enums\BudgetDepartment;
use App\Enums\BudgetStatus;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'department',
    'fiscal_year',
    'status',
    'created_by',
])]
class Budget extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'department' => BudgetDepartment::class,
            'status' => BudgetStatus::class,
            'fiscal_year' => 'integer',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }
}
