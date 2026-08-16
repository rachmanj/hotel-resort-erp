<?php

namespace App\Models;

use App\Enums\AgentType;
use App\Enums\CommissionBasis;
use App\Enums\CommissionType;
use App\Models\Concerns\BelongsToHotel;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'agent_type',
    'name',
    'code',
    'channel_code',
    'contact_person',
    'phone',
    'email',
    'commission_percent',
    'commission_type',
    'commission_flat_amount',
    'commission_basis',
    'payment_terms_days',
    'company_id',
    'user_id',
    'api_config',
    'is_active',
])]
class Agent extends Model
{
    use BelongsToHotel, LogsActivity;

    protected $attributes = [
        'commission_type' => CommissionType::Percent->value,
    ];

    protected function casts(): array
    {
        return [
            'agent_type' => AgentType::class,
            'commission_basis' => CommissionBasis::class,
            'commission_type' => CommissionType::class,
            'commission_percent' => 'decimal:2',
            'commission_flat_amount' => 'decimal:2',
            'api_config' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(AgentRate::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AgentCommission::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
