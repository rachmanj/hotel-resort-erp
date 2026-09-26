<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bank_reconciliation_match_id',
    'bank_reconciliation_book_line_id',
])]
class BankReconciliationMatchLedger extends Model
{
    protected $table = 'bank_reconciliation_match_ledger';

    public function match(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationMatch::class, 'bank_reconciliation_match_id');
    }

    public function bookLine(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationBookLine::class, 'bank_reconciliation_book_line_id');
    }
}
