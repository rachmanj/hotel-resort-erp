<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'bank_reconciliation_id',
    'bank_reconciliation_match_id',
    'action',
    'bank_line_ids',
    'book_line_ids',
    'amounts',
    'performed_by',
    'notes',
])]
class BankReconciliationAudit extends Model
{
    protected function casts(): array
    {
        return [
            'bank_line_ids' => 'array',
            'book_line_ids' => 'array',
            'amounts' => 'array',
        ];
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
