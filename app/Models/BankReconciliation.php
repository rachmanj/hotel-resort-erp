<?php

namespace App\Models;

use App\Enums\BankReconciliationStatus;
use App\Enums\BankReconciliationValidationStatus;
use Database\Factories\BankReconciliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'bank_account_id',
    'periode',
    'period_end_date',
    'statement_opening_balance',
    'statement_closing_balance',
    'book_opening_balance',
    'book_closing_balance',
    'statement_balance',
    'book_balance',
    'statement_source',
    'statement_hash',
    'statement_format',
    'source_mode',
    'status',
    'created_by',
    'submitted_by',
    'submitted_at',
    'validated_by',
    'validated_at',
    'validation_status',
    'rejection_reason',
    'reconciled_by',
    'reconciled_at',
    'finalized_at',
    'notes',
    'reopen_reason',
])]
class BankReconciliation extends Model
{
    /** @use HasFactory<BankReconciliationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'period_end_date' => 'date',
            'statement_opening_balance' => 'decimal:2',
            'statement_closing_balance' => 'decimal:2',
            'book_opening_balance' => 'decimal:2',
            'book_closing_balance' => 'decimal:2',
            'statement_balance' => 'decimal:2',
            'book_balance' => 'decimal:2',
            'status' => BankReconciliationStatus::class,
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'validation_status' => BankReconciliationValidationStatus::class,
            'reconciled_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function isLockedForEditing(): bool
    {
        return $this->status->isLockedForEditing();
    }

    public function isPreparer(int $userId): bool
    {
        return in_array($userId, array_filter([
            $this->created_by,
            $this->submitted_by,
            $this->validated_by,
        ]), true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExcludingPreparer(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $builder) use ($userId): void {
            $builder->where(function (Builder $inner) use ($userId): void {
                $inner->whereNull('created_by')
                    ->orWhere('created_by', '!=', $userId);
            })->where(function (Builder $inner) use ($userId): void {
                $inner->whereNull('submitted_by')
                    ->orWhere('submitted_by', '!=', $userId);
            })->where(function (Builder $inner) use ($userId): void {
                $inner->whereNull('validated_by')
                    ->orWhere('validated_by', '!=', $userId);
            });
        });
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankReconciliationLine::class);
    }

    public function bookLines(): HasMany
    {
        return $this->hasMany(BankReconciliationBookLine::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankReconciliationMatch::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(BankReconciliationAudit::class);
    }
}
