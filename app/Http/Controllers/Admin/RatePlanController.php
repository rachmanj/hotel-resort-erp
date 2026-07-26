<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RatePlanType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRatePlanRequest;
use App\Http\Requests\Admin\UpdateRatePlanRequest;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RatePlanController extends Controller
{
    public function index(Request $request): Response
    {
        $ratePlans = RatePlan::query()
            ->with(['roomType:id,name,code', 'season:id,name'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (RatePlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'rate_type' => $plan->rate_type->value,
                'rate_type_label' => $plan->rate_type->label(),
                'nightly_rate' => $plan->nightly_rate,
                'is_active' => $plan->is_active,
                'valid_from' => $plan->valid_from?->toDateString(),
                'valid_to' => $plan->valid_to?->toDateString(),
                'room_type' => $plan->roomType?->only(['id', 'name', 'code']),
                'season' => $plan->season?->only(['id', 'name']),
            ]);

        return Inertia::render('Admin/RatePlans/Index', [
            'ratePlans' => $ratePlans,
            'roomTypes' => RoomType::query()->orderBy('name')->get(['id', 'name', 'code']),
            'seasons' => Season::query()->orderBy('name')->get(['id', 'name']),
            'rateTypes' => collect(RatePlanType::cases())->map(fn (RatePlanType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreRatePlanRequest $request): RedirectResponse
    {
        RatePlan::query()->create($request->validated());

        return back()->with('success', 'Rate plan created successfully.');
    }

    public function update(UpdateRatePlanRequest $request, RatePlan $ratePlan): RedirectResponse
    {
        $ratePlan->update($request->validated());

        return back()->with('success', 'Rate plan updated successfully.');
    }

    public function destroy(RatePlan $ratePlan): RedirectResponse
    {
        $ratePlan->delete();

        return back()->with('success', 'Rate plan deleted successfully.');
    }
}
