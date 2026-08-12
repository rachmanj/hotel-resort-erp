<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromotionCodeRequest;
use App\Models\Promotion;
use App\Models\PromotionCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class PromotionCodeController extends Controller
{
    public function index(Promotion $promotion): JsonResponse
    {
        $codes = $promotion->codes()
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (PromotionCode $code) => [
                'id' => $code->id,
                'code' => $code->code,
                'max_uses' => $code->max_uses,
                'used_count' => $code->used_count,
                'is_active' => $code->is_active,
                'expires_at' => $code->expires_at?->toDateTimeString(),
            ]);

        return response()->json(['codes' => $codes]);
    }

    public function store(StorePromotionCodeRequest $request, Promotion $promotion): RedirectResponse
    {
        $code = $request->validated('code') ?? Str::upper(Str::random(8));

        PromotionCode::query()->create([
            'promotion_id' => $promotion->id,
            'code' => Str::upper($code),
            'max_uses' => $request->validated('max_uses'),
            'expires_at' => $request->validated('expires_at'),
            'is_active' => true,
        ]);

        return back()->with('success', "Promotion code {$code} generated successfully.");
    }

    public function destroy(PromotionCode $code): RedirectResponse
    {
        $code->delete();

        return back()->with('success', 'Promotion code deleted successfully.');
    }
}
