<?php

namespace App\Models;

use App\Enums\AgentCommissionStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'agent_id',
    'reservation_id',
    'folio_id',
    'base_amount',
    'commission_percent',
    'commission_type',
    'commission_flat_amount',
    'commission_amount',
    'status',
    'ar_invoice_id',
    'deduction_folio_item_id',
    'earned_at',
])]
class AgentCommission extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => AgentCommissionStatus::class,
            'base_amount' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'commission_type' => 'string',
            'commission_flat_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'earned_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(Folio::class);
    }

    public function arInvoice(): BelongsTo
    {
        return $this->belongsTo(ArInvoice::class);
    }

    public function deductionFolioItem(): BelongsTo
    {
        return $this->belongsTo(FolioItem::class, 'deduction_folio_item_id');
    }
}
