<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PromoDiscountType;
use App\Enums\PromotionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePromotionRequest;
use App\Http\Requests\Admin\UpdatePromotionRequest;
use App\Models\Company;
use App\Models\MenuItem;
use App\Models\Promotion;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\SpaTreatment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        $promotions = Promotion::query()
            ->withCount('codes')
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderByDesc('valid_from')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Promotion $promotion) => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'promo_type' => $promotion->promo_type->value,
                'promo_type_label' => $promotion->promo_type->label(),
                'discount_summary' => $promotion->discountSummary(),
                'valid_from' => $promotion->valid_from->toDateString(),
                'valid_to' => $promotion->valid_to->toDateString(),
                'used_count' => $promotion->used_count,
                'max_uses' => $promotion->max_uses,
                'is_active' => $promotion->is_active,
                'requires_code' => $promotion->requires_code,
                'codes_count' => $promotion->codes_count,
            ]);

        return Inertia::render('Admin/Promotions/Index', [
            'promotions' => $promotions,
            'roomTypes' => RoomType::query()->orderBy('name')->get(['id', 'name', 'code']),
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ratePlans' => RatePlan::query()->orderBy('name')->get(['id', 'name', 'nightly_rate']),
            'promoTypes' => collect(PromotionType::cases())->map(fn (PromotionType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'discountTypes' => collect(PromoDiscountType::cases())->map(fn (PromoDiscountType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'menuItems' => MenuItem::query()->where('is_available', true)->orderBy('name')->get(['id', 'name', 'price']),
            'spaTreatments' => SpaTreatment::query()->orderBy('name')->get(['id', 'name', 'price']),
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(Promotion $promotion): JsonResponse
    {
        $promotion->load(['conditions', 'roomTypes', 'packageItems']);

        return response()->json([
            'promotion' => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'promo_type' => $promotion->promo_type->value,
                'discount_type' => $promotion->discount_type->value,
                'discount_value' => (float) $promotion->discount_value,
                'rate_plan_id' => $promotion->rate_plan_id,
                'company_id' => $promotion->company_id,
                'lead_time_min_days' => $promotion->lead_time_min_days,
                'lead_time_max_days' => $promotion->lead_time_max_days,
                'min_nights' => $promotion->min_nights,
                'max_nights' => $promotion->max_nights,
                'valid_from' => $promotion->valid_from->toDateString(),
                'valid_to' => $promotion->valid_to->toDateString(),
                'is_stackable' => $promotion->is_stackable,
                'requires_code' => $promotion->requires_code,
                'max_uses' => $promotion->max_uses,
                'is_active' => $promotion->is_active,
                'room_type_ids' => $promotion->roomTypes->pluck('id'),
                'conditions' => $promotion->conditions->map(fn ($c) => [
                    'condition_type' => $c->condition_type->value,
                    'value' => $c->value,
                ]),
                'package_items' => $promotion->packageItems->map(fn ($i) => [
                    'item_type' => $i->item_type->value,
                    'reference_id' => $i->reference_id,
                    'quantity' => $i->quantity,
                    'package_value' => (float) $i->package_value,
                ]),
            ],
        ]);
    }

    public function store(StorePromotionRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = collect($request->validated())->except(['room_type_ids', 'conditions', 'package_items'])->all();
            $promotion = Promotion::query()->create($data);

            $this->syncRelations($promotion, $request);
        });

        return back()->with('success', 'Promotion created successfully.');
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): RedirectResponse
    {
        DB::transaction(function () use ($request, $promotion): void {
            $data = collect($request->validated())->except(['room_type_ids', 'conditions', 'package_items'])->all();
            $promotion->update($data);

            $promotion->conditions()->delete();
            $promotion->packageItems()->delete();
            $this->syncRelations($promotion, $request);
        });

        return back()->with('success', 'Promotion updated successfully.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $promotion->delete();

        return back()->with('success', 'Promotion deleted successfully.');
    }

    private function syncRelations(Promotion $promotion, StorePromotionRequest $request): void
    {
        if ($request->has('room_type_ids')) {
            $promotion->roomTypes()->sync($request->input('room_type_ids', []));
        }

        foreach ($request->input('conditions', []) as $condition) {
            $promotion->conditions()->create([
                'condition_type' => $condition['condition_type'],
                'value' => $condition['value'],
            ]);
        }

        foreach ($request->input('package_items', []) as $item) {
            $promotion->packageItems()->create([
                'item_type' => $item['item_type'],
                'reference_id' => $item['reference_id'],
                'quantity' => $item['quantity'],
                'package_value' => $item['package_value'],
            ]);
        }
    }
}
