<?php

namespace App\Services;

use App\Enums\PromoDiscountType;
use App\Enums\PromotionConditionType;
use App\Enums\PromotionType;
use App\Models\Company;
use App\Models\Promotion;
use App\Models\PromotionCode;
use App\Models\PromotionRedemption;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PromotionEngine
{
    /**
     * @return Collection<int, Promotion>
     */
    public function findApplicable(
        RoomType $roomType,
        Carbon $checkin,
        Carbon $checkout,
        ?Company $company = null,
        ?string $code = null,
        ?int $hotelId = null,
    ): Collection {
        $nights = max(1, $checkin->diffInDays($checkout));
        $bookingDate = now()->startOfDay();
        $leadDays = $bookingDate->diffInDays($checkin, false);

        $query = Promotion::query()
            ->with(['conditions', 'roomTypes', 'codes'])
            ->where('is_active', true)
            ->where('valid_from', '<=', $checkin->toDateString())
            ->where('valid_to', '>=', $checkin->toDateString());

        if ($hotelId !== null) {
            $query->where(function ($q) use ($hotelId): void {
                $q->where('hotel_id', $hotelId)->orWhereNull('hotel_id');
            });
        }

        $promotions = $query->get();

        return $promotions->filter(function (Promotion $promotion) use (
            $roomType,
            $checkin,
            $nights,
            $leadDays,
            $company,
            $code,
        ): bool {
            if (! $this->passesUsageLimit($promotion)) {
                return false;
            }

            if (! $this->passesRoomTypeScope($promotion, $roomType)) {
                return false;
            }

            if (! $this->passesCompanyScope($promotion, $company)) {
                return false;
            }

            if (! $this->passesLeadTime($promotion, $leadDays)) {
                return false;
            }

            if (! $this->passesNightConstraints($promotion, $nights)) {
                return false;
            }

            if (! $this->passesConditions($promotion, $checkin, $nights)) {
                return false;
            }

            if ($promotion->requires_code) {
                return $this->passesCodeRequirement($promotion, $code);
            }

            if ($code !== null && $code !== '' && $promotion->codes->isNotEmpty()) {
                return $this->passesCodeRequirement($promotion, $code);
            }

            return true;
        })->values();
    }

    /**
     * @param  Collection<int, Promotion>  $applicablePromotions
     * @return array{
     *     nightly_rate: string,
     *     gross_nightly_rate: string,
     *     promotion_id: int|null,
     *     promotion_code_id: int|null,
     *     discount_amount: float,
     *     applied_promotions: list<array{promotion: Promotion, discount_amount: float, promotion_code_id: int|null}>
     * }
     */
    public function resolveBestRate(
        RoomType $roomType,
        float $baseNightlyRate,
        Collection $applicablePromotions,
        int $nights,
        ?string $code = null,
    ): array {
        if ($applicablePromotions->isEmpty()) {
            return [
                'nightly_rate' => (string) round($baseNightlyRate, 2),
                'gross_nightly_rate' => (string) round($baseNightlyRate, 2),
                'promotion_id' => null,
                'promotion_code_id' => null,
                'discount_amount' => 0.0,
                'applied_promotions' => [],
            ];
        }

        $stackable = $applicablePromotions->filter(fn (Promotion $p) => $p->is_stackable);
        $nonStackable = $applicablePromotions->filter(fn (Promotion $p) => ! $p->is_stackable);

        $bestNonStackable = null;
        $bestNonStackableDiscount = 0.0;
        $bestNonStackableCodeId = null;

        foreach ($nonStackable as $promotion) {
            $result = $this->calculateDiscount($promotion, $baseNightlyRate, $nights);
            if ($result['total_discount'] > $bestNonStackableDiscount) {
                $bestNonStackableDiscount = $result['total_discount'];
                $bestNonStackable = $promotion;
                $bestNonStackableCodeId = $this->resolveCodeId($promotion, $code);
            }
        }

        if ($stackable->isEmpty()) {
            if ($bestNonStackable === null) {
                return [
                    'nightly_rate' => (string) round($baseNightlyRate, 2),
                    'gross_nightly_rate' => (string) round($baseNightlyRate, 2),
                    'promotion_id' => null,
                    'promotion_code_id' => null,
                    'discount_amount' => 0.0,
                    'applied_promotions' => [],
                ];
            }

            $result = $this->calculateDiscount($bestNonStackable, $baseNightlyRate, $nights);

            return [
                'nightly_rate' => (string) round($result['nightly_rate'], 2),
                'gross_nightly_rate' => (string) round($baseNightlyRate, 2),
                'promotion_id' => $bestNonStackable->id,
                'promotion_code_id' => $bestNonStackableCodeId,
                'discount_amount' => $result['total_discount'],
                'applied_promotions' => [[
                    'promotion' => $bestNonStackable,
                    'discount_amount' => $result['total_discount'],
                    'promotion_code_id' => $bestNonStackableCodeId,
                ]],
            ];
        }

        $currentRate = $baseNightlyRate;
        $applied = [];
        $totalDiscount = 0.0;
        $primaryPromotionId = null;
        $primaryCodeId = null;

        foreach ($stackable as $promotion) {
            $result = $this->calculateDiscount($promotion, $currentRate, $nights);
            $codeId = $this->resolveCodeId($promotion, $code);
            $applied[] = [
                'promotion' => $promotion,
                'discount_amount' => $result['total_discount'],
                'promotion_code_id' => $codeId,
            ];
            $totalDiscount += $result['total_discount'];
            $currentRate = $result['nightly_rate'];

            if ($primaryPromotionId === null) {
                $primaryPromotionId = $promotion->id;
                $primaryCodeId = $codeId;
            }
        }

        if ($bestNonStackable !== null) {
            $nsResult = $this->calculateDiscount($bestNonStackable, $baseNightlyRate, $nights);
            if ($nsResult['total_discount'] > $totalDiscount) {
                return [
                    'nightly_rate' => (string) round($nsResult['nightly_rate'], 2),
                    'gross_nightly_rate' => (string) round($baseNightlyRate, 2),
                    'promotion_id' => $bestNonStackable->id,
                    'promotion_code_id' => $bestNonStackableCodeId,
                    'discount_amount' => $nsResult['total_discount'],
                    'applied_promotions' => [[
                        'promotion' => $bestNonStackable,
                        'discount_amount' => $nsResult['total_discount'],
                        'promotion_code_id' => $bestNonStackableCodeId,
                    ]],
                ];
            }
        }

        return [
            'nightly_rate' => (string) round(max(0, $currentRate), 2),
            'gross_nightly_rate' => (string) round($baseNightlyRate, 2),
            'promotion_id' => $primaryPromotionId,
            'promotion_code_id' => $primaryCodeId,
            'discount_amount' => round($totalDiscount, 2),
            'applied_promotions' => $applied,
        ];
    }

    public function redeem(
        Promotion $promotion,
        Reservation $reservation,
        ?ReservationRoom $room,
        ?PromotionCode $code,
        float $discountAmount,
    ): void {
        DB::transaction(function () use ($promotion, $reservation, $room, $code, $discountAmount): void {
            $lockedPromotion = Promotion::query()->lockForUpdate()->findOrFail($promotion->id);

            if ($lockedPromotion->max_uses !== null && $lockedPromotion->used_count >= $lockedPromotion->max_uses) {
                throw new \InvalidArgumentException('Promotion has reached its maximum usage limit.');
            }

            $lockedPromotion->increment('used_count');

            if ($code !== null) {
                $lockedCode = PromotionCode::query()->lockForUpdate()->findOrFail($code->id);

                if ($lockedCode->max_uses !== null && $lockedCode->used_count >= $lockedCode->max_uses) {
                    throw new \InvalidArgumentException('Promotion code has reached its maximum usage limit.');
                }

                $lockedCode->increment('used_count');
            }

            PromotionRedemption::query()->create([
                'promotion_id' => $lockedPromotion->id,
                'promotion_code_id' => $code?->id,
                'reservation_id' => $reservation->id,
                'reservation_room_id' => $room?->id,
                'discount_amount' => round($discountAmount, 2),
                'redeemed_at' => now(),
            ]);
        });
    }

    public function resolveBaseNightlyRate(?int $ratePlanId, RoomType $roomType): float
    {
        if ($ratePlanId !== null) {
            $ratePlan = RatePlan::query()->findOrFail($ratePlanId);

            return (float) $ratePlan->nightly_rate;
        }

        return (float) $roomType->base_rate;
    }

    /**
     * @return array{nightly_rate: float, total_discount: float}
     */
    private function calculateDiscount(Promotion $promotion, float $baseNightlyRate, int $nights): array
    {
        return match ($promotion->discount_type) {
            PromoDiscountType::Percent => $this->calculatePercentDiscount($baseNightlyRate, $nights, (float) $promotion->discount_value),
            PromoDiscountType::Fixed => $this->calculateFixedDiscount($baseNightlyRate, $nights, (float) $promotion->discount_value),
            PromoDiscountType::PackagePrice => $this->calculatePackageDiscount($baseNightlyRate, $nights, (float) $promotion->discount_value),
        };
    }

    /**
     * @return array{nightly_rate: float, total_discount: float}
     */
    private function calculatePercentDiscount(float $baseNightlyRate, int $nights, float $percent): array
    {
        $discountPerNight = round($baseNightlyRate * ($percent / 100), 2);
        $nightlyRate = max(0, $baseNightlyRate - $discountPerNight);

        return [
            'nightly_rate' => $nightlyRate,
            'total_discount' => round($discountPerNight * $nights, 2),
        ];
    }

    /**
     * @return array{nightly_rate: float, total_discount: float}
     */
    private function calculateFixedDiscount(float $baseNightlyRate, int $nights, float $fixedAmount): array
    {
        $discountPerNight = min($fixedAmount, $baseNightlyRate);
        $nightlyRate = max(0, $baseNightlyRate - $discountPerNight);

        return [
            'nightly_rate' => $nightlyRate,
            'total_discount' => round($discountPerNight * $nights, 2),
        ];
    }

    /**
     * @return array{nightly_rate: float, total_discount: float}
     */
    private function calculatePackageDiscount(float $baseNightlyRate, int $nights, float $packagePrice): array
    {
        $totalBase = $baseNightlyRate * $nights;
        $totalDiscount = max(0, $totalBase - $packagePrice);
        $nightlyRate = max(0, ($totalBase - $totalDiscount) / $nights);

        return [
            'nightly_rate' => round($nightlyRate, 2),
            'total_discount' => round($totalDiscount, 2),
        ];
    }

    private function passesUsageLimit(Promotion $promotion): bool
    {
        return $promotion->max_uses === null || $promotion->used_count < $promotion->max_uses;
    }

    private function passesRoomTypeScope(Promotion $promotion, RoomType $roomType): bool
    {
        if ($promotion->roomTypes->isEmpty()) {
            return true;
        }

        return $promotion->roomTypes->contains('id', $roomType->id);
    }

    private function passesCompanyScope(Promotion $promotion, ?Company $company): bool
    {
        if ($promotion->promo_type !== PromotionType::Corporate) {
            return true;
        }

        if ($promotion->company_id === null) {
            return true;
        }

        return $company !== null && $company->id === $promotion->company_id;
    }

    private function passesLeadTime(Promotion $promotion, int $leadDays): bool
    {
        if ($promotion->promo_type === PromotionType::EarlyBird) {
            if ($promotion->lead_time_min_days !== null && $leadDays < $promotion->lead_time_min_days) {
                return false;
            }
        }

        if ($promotion->promo_type === PromotionType::LastMinute) {
            if ($promotion->lead_time_max_days !== null && $leadDays > $promotion->lead_time_max_days) {
                return false;
            }
        }

        return true;
    }

    private function passesNightConstraints(Promotion $promotion, int $nights): bool
    {
        if ($promotion->min_nights !== null && $nights < $promotion->min_nights) {
            return false;
        }

        if ($promotion->max_nights !== null && $nights > $promotion->max_nights) {
            return false;
        }

        return true;
    }

    private function passesConditions(Promotion $promotion, Carbon $checkin, int $nights): bool
    {
        foreach ($promotion->conditions as $condition) {
            $value = $condition->value;

            $passes = match ($condition->condition_type) {
                PromotionConditionType::DayOfWeek => in_array(
                    strtolower($checkin->englishDayOfWeek),
                    array_map('strtolower', $value),
                    true,
                ),
                PromotionConditionType::BlackoutDate => ! in_array(
                    $checkin->toDateString(),
                    $value,
                    true,
                ),
                PromotionConditionType::MinLos => $nights >= (int) ($value[0] ?? 0),
                PromotionConditionType::MaxLos => $nights <= (int) ($value[0] ?? PHP_INT_MAX),
            };

            if (! $passes) {
                return false;
            }
        }

        return true;
    }

    private function passesCodeRequirement(Promotion $promotion, ?string $code): bool
    {
        if ($code === null || $code === '') {
            return ! $promotion->requires_code;
        }

        $promotionCode = $promotion->codes
            ->first(fn (PromotionCode $c) => strtoupper($c->code) === strtoupper($code)
                && $c->is_active
                && ! $c->isExhausted()
                && ! $c->isExpired());

        return $promotionCode !== null;
    }

    private function resolveCodeId(Promotion $promotion, ?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }

        $promotionCode = $promotion->codes
            ->first(fn (PromotionCode $c) => strtoupper($c->code) === strtoupper($code));

        return $promotionCode?->id;
    }
}
