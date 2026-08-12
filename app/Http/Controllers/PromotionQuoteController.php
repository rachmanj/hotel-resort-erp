<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\RoomType;
use App\Services\PromotionEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionQuoteController extends Controller
{
    public function store(Request $request, PromotionEngine $promotionEngine): JsonResponse
    {
        $validated = $request->validate([
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'arrival_date' => ['required', 'date'],
            'departure_date' => ['required', 'date', 'after:arrival_date'],
            'rate_plan_id' => ['nullable', 'integer', 'exists:rate_plans,id'],
            'promotion_code' => ['nullable', 'string', 'max:30'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        $hotelId = session('current_hotel_id');
        $checkin = Carbon::parse($validated['arrival_date'])->startOfDay();
        $checkout = Carbon::parse($validated['departure_date'])->startOfDay();
        $nights = max(1, $checkin->diffInDays($checkout));

        $roomType = RoomType::query()->findOrFail($validated['room_type_id']);
        $company = isset($validated['company_id'])
            ? Company::query()->find($validated['company_id'])
            : null;

        $baseRate = $promotionEngine->resolveBaseNightlyRate(
            $validated['rate_plan_id'] ?? null,
            $roomType,
        );

        $applicable = $promotionEngine->findApplicable(
            $roomType,
            $checkin,
            $checkout,
            $company,
            $validated['promotion_code'] ?? null,
            $hotelId,
        );

        $resolved = $promotionEngine->resolveBestRate(
            $roomType,
            $baseRate,
            $applicable,
            $nights,
            $validated['promotion_code'] ?? null,
        );

        return response()->json([
            'base_nightly_rate' => $baseRate,
            'nightly_rate' => $resolved['nightly_rate'],
            'gross_nightly_rate' => $resolved['gross_nightly_rate'],
            'discount_amount' => $resolved['discount_amount'],
            'nights' => $nights,
            'promotion_id' => $resolved['promotion_id'],
            'applicable_promotions' => $applicable->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'promo_type_label' => $p->promo_type->label(),
                'discount_summary' => $p->discountSummary(),
            ]),
            'applied_promotion' => $resolved['promotion_id'] !== null
                ? $applicable->firstWhere('id', $resolved['promotion_id'])?->only(['id', 'name'])
                : null,
        ]);
    }
}
