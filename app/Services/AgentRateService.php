<?php

namespace App\Services;

use App\Enums\AgentRateDiscountType;
use App\Models\Agent;
use App\Models\AgentRate;
use App\Models\RatePlan;
use App\Models\RoomType;
use Carbon\CarbonInterface;

class AgentRateService
{
    public function resolveNightlyRate(
        Agent $agent,
        int $roomTypeId,
        CarbonInterface $checkin,
        CarbonInterface $checkout,
        ?int $ratePlanId = null,
    ): ?string {
        $rate = AgentRate::query()
            ->where('agent_id', $agent->id)
            ->where('room_type_id', $roomTypeId)
            ->where('is_active', true)
            ->where('valid_from', '<=', $checkin->toDateString())
            ->where('valid_to', '>=', $checkout->copy()->subDay()->toDateString())
            ->when($ratePlanId !== null, fn ($q) => $q->where(function ($query) use ($ratePlanId): void {
                $query->where('rate_plan_id', $ratePlanId)->orWhereNull('rate_plan_id');
            }))
            ->orderByDesc('valid_from')
            ->first();

        if ($rate === null) {
            return null;
        }

        if ($rate->rate_plan_id !== null) {
            $ratePlan = RatePlan::query()->find($rate->rate_plan_id);

            return $ratePlan !== null ? (string) $ratePlan->nightly_rate : null;
        }

        if ($rate->nightly_rate !== null) {
            return (string) $rate->nightly_rate;
        }

        if ($rate->discount_type !== null && $rate->discount_value !== null) {
            $roomType = RoomType::query()->findOrFail($roomTypeId);
            $baseRate = (float) $roomType->base_rate;

            $discounted = match ($rate->discount_type) {
                AgentRateDiscountType::Percent => $baseRate * (1 - ((float) $rate->discount_value / 100)),
                AgentRateDiscountType::Fixed => max(0, $baseRate - (float) $rate->discount_value),
            };

            return (string) round($discounted, 2);
        }

        return null;
    }
}
