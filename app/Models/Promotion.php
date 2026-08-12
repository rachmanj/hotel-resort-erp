<?php

namespace App\Models;

use App\Enums\PromoDiscountType;
use App\Enums\PromotionType;
use App\Models\Concerns\BelongsToHotel;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hotel_id',
    'name',
    'promo_type',
    'discount_type',
    'discount_value',
    'rate_plan_id',
    'company_id',
    'lead_time_min_days',
    'lead_time_max_days',
    'min_nights',
    'max_nights',
    'valid_from',
    'valid_to',
    'is_stackable',
    'requires_code',
    'max_uses',
    'used_count',
    'is_active',
])]
class Promotion extends Model
{
    use BelongsToHotel, LogsActivity;

    protected static function nullableHotelId(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'promo_type' => PromotionType::class,
            'discount_type' => PromoDiscountType::class,
            'discount_value' => 'decimal:2',
            'valid_from' => 'date',
            'valid_to' => 'date',
            'is_stackable' => 'boolean',
            'requires_code' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(PromotionCondition::class);
    }

    public function codes(): HasMany
    {
        return $this->hasMany(PromotionCode::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function packageItems(): HasMany
    {
        return $this->hasMany(PromotionPackageItem::class);
    }

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class, 'promotion_room_types');
    }

    public function discountSummary(): string
    {
        return match ($this->discount_type) {
            PromoDiscountType::Percent => number_format((float) $this->discount_value, 0).'% off',
            PromoDiscountType::Fixed => 'Rp '.number_format((float) $this->discount_value, 0, ',', '.').' off',
            PromoDiscountType::PackagePrice => 'Package: Rp '.number_format((float) $this->discount_value, 0, ',', '.'),
        };
    }
}
