<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bank_reconciliation_match_id',
    'bank_reconciliation_line_id',
])]
class BankReconciliationMatchLine extends Model
{
    public function match(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationMatch::class, 'bank_reconciliation_match_id');
    }

    public function bankReconciliationLine(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationLine::class);
    }
}
