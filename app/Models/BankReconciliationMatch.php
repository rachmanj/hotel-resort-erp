<?php

namespace App\Models;

use App\Enums\BankMatchType;
use Database\Factories\BankReconciliationMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'bank_reconciliation_id',
    'match_type',
    'confidence_score',
    'bank_total',
    'book_total',
    'difference',
    'notes',
    'created_by',
])]
class BankReconciliationMatch extends Model
{
    /** @use HasFactory<BankReconciliationMatchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'match_type' => BankMatchType::class,
            'confidence_score' => 'float',
            'bank_total' => 'decimal:2',
            'book_total' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function matchLines(): HasMany
    {
        return $this->hasMany(BankReconciliationMatchLine::class);
    }

    public function matchLedgerRows(): HasMany
    {
        return $this->hasMany(BankReconciliationMatchLedger::class);
    }

    public function lines(): BelongsToMany
    {
        return $this->belongsToMany(
            BankReconciliationLine::class,
            'bank_reconciliation_match_lines',
            'bank_reconciliation_match_id',
            'bank_reconciliation_line_id',
        )->withTimestamps();
    }

    public function ledgerLines(): BelongsToMany
    {
        return $this->belongsToMany(
            BankReconciliationBookLine::class,
            'bank_reconciliation_match_ledger',
            'bank_reconciliation_match_id',
            'bank_reconciliation_book_line_id',
        )->withTimestamps();
    }
}
