<?php

namespace App\Models;

use App\Enums\BankReconciliationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'bank_account_id',
    'period_end_date',
    'statement_balance',
    'book_balance',
    'status',
    'reconciled_by',
    'reconciled_at',
])]
class BankReconciliation extends Model
{
    protected function casts(): array
    {
        return [
            'period_end_date' => 'date',
            'statement_balance' => 'decimal:2',
            'book_balance' => 'decimal:2',
            'status' => BankReconciliationStatus::class,
            'reconciled_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankReconciliationLine::class);
    }
}
