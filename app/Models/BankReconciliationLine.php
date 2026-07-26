<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bank_reconciliation_id',
    'general_ledger_id',
    'statement_line_ref',
    'statement_date',
    'statement_amount',
    'is_matched',
    'matched_at',
])]
class BankReconciliationLine extends Model
{
    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'statement_amount' => 'decimal:2',
            'is_matched' => 'boolean',
            'matched_at' => 'datetime',
        ];
    }

    public function bankReconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class);
    }

    public function generalLedger(): BelongsTo
    {
        return $this->belongsTo(GeneralLedger::class);
    }
}
