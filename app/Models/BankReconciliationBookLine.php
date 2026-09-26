<?php

namespace App\Models;

use App\Enums\BankBookLineStatus;
use Database\Factories\BankReconciliationBookLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'bank_reconciliation_id',
    'general_ledger_id',
    'posting_date',
    'doc_num',
    'reference_number',
    'description',
    'debit',
    'credit',
    'match_status',
    'exclude_reason',
    'line_notes',
    'is_carried_forward',
    'carried_from_book_line_id',
    'origin_reconciliation_id',
    'is_stale',
    'stale_reason',
])]
class BankReconciliationBookLine extends Model
{
    /** @use HasFactory<BankReconciliationBookLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'match_status' => BankBookLineStatus::class,
            'is_carried_forward' => 'boolean',
            'is_stale' => 'boolean',
        ];
    }

    public function netAmount(): float
    {
        return round((float) $this->debit - (float) $this->credit, 2);
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function generalLedger(): BelongsTo
    {
        return $this->belongsTo(GeneralLedger::class);
    }

    public function matchLedger(): HasOne
    {
        return $this->hasOne(BankReconciliationMatchLedger::class);
    }

    public function carriedFromBookLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_from_book_line_id');
    }
}
