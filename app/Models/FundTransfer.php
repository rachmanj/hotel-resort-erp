<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hotel_id',
    'transfer_no',
    'from_chart_of_account_id',
    'to_chart_of_account_id',
    'from_bank_account_id',
    'to_bank_account_id',
    'amount',
    'transfer_date',
    'description',
    'created_by',
])]
class FundTransfer extends Model
{
    use BelongsToHotel;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transfer_date' => 'date',
        ];
    }

    public function fromChartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'from_chart_of_account_id');
    }

    public function toChartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'to_chart_of_account_id');
    }

    public function fromBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'from_bank_account_id');
    }

    public function toBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'to_bank_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
