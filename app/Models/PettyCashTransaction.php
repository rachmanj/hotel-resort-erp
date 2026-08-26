<?php

namespace App\Models;

use App\Enums\PettyCashDirection;
use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hotel_id',
    'bank_account_id',
    'direction',
    'amount',
    'transaction_date',
    'department_id',
    'chart_of_account_id',
    'description',
    'reference_no',
    'created_by',
])]
class PettyCashTransaction extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'direction' => PettyCashDirection::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
