<?php

namespace App\Models;

use App\Enums\BankStatementLineStatus;
use Database\Factories\BankReconciliationLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'bank_reconciliation_id',
    'posting_date',
    'value_date',
    'general_ledger_id',
    'statement_line_ref',
    'description',
    'reference',
    'statement_date',
    'statement_amount',
    'debit',
    'credit',
    'amount',
    'direction',
    'running_balance',
    'is_matched',
    'match_status',
    'exclude_reason',
    'line_notes',
    'line_order',
    'line_hash',
    'is_ai_extracted',
    'ai_meta',
    'adjusting_journal_id',
    'matched_at',
    'is_carried_forward',
    'carried_from_line_id',
    'origin_reconciliation_id',
])]
class BankReconciliationLine extends Model
{
    /** @use HasFactory<BankReconciliationLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
            'value_date' => 'date',
            'statement_date' => 'date',
            'statement_amount' => 'decimal:2',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'amount' => 'decimal:2',
            'running_balance' => 'decimal:2',
            'is_matched' => 'boolean',
            'match_status' => BankStatementLineStatus::class,
            'is_ai_extracted' => 'boolean',
            'ai_meta' => 'array',
            'matched_at' => 'datetime',
            'is_carried_forward' => 'boolean',
        ];
    }

    public function netAmount(): float
    {
        return round((float) $this->debit - (float) $this->credit, 2);
    }

    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    public function generalLedger(): BelongsTo
    {
        return $this->belongsTo(GeneralLedger::class);
    }

    public function adjustingJournal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'adjusting_journal_id');
    }

    public function matchLine(): HasOne
    {
        return $this->hasOne(BankReconciliationMatchLine::class);
    }

    public function carriedFromLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_from_line_id');
    }
}
