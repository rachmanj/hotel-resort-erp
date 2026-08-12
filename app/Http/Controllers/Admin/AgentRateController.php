<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AgentRateDiscountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAgentRateRequest;
use App\Http\Requests\Admin\UpdateAgentRateRequest;
use App\Models\Agent;
use App\Models\AgentRate;
use App\Models\RatePlan;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentRateController extends Controller
{
    public function index(Request $request, Agent $agent): Response
    {
        $rates = AgentRate::query()
            ->where('agent_id', $agent->id)
            ->with(['roomType:id,name,code', 'ratePlan:id,name'])
            ->orderByDesc('valid_from')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AgentRate $rate) => [
                'id' => $rate->id,
                'room_type' => $rate->roomType?->only(['id', 'name', 'code']),
                'rate_plan' => $rate->ratePlan?->only(['id', 'name']),
                'nightly_rate' => $rate->nightly_rate,
                'discount_type' => $rate->discount_type?->value,
                'discount_type_label' => $rate->discount_type?->label(),
                'discount_value' => $rate->discount_value,
                'valid_from' => $rate->valid_from?->toDateString(),
                'valid_to' => $rate->valid_to?->toDateString(),
                'is_active' => $rate->is_active,
            ]);

        return Inertia::render('Admin/Agents/Rates', [
            'agent' => $agent->only(['id', 'name', 'code']),
            'rates' => $rates,
            'roomTypes' => RoomType::query()->orderBy('name')->get(['id', 'name', 'code']),
            'ratePlans' => RatePlan::query()->orderBy('name')->get(['id', 'name']),
            'discountTypes' => collect(AgentRateDiscountType::cases())->map(fn (AgentRateDiscountType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function store(StoreAgentRateRequest $request, Agent $agent): RedirectResponse
    {
        $agent->rates()->create($request->validated());

        return back()->with('success', 'Agent rate created successfully.');
    }

    public function update(UpdateAgentRateRequest $request, AgentRate $rate): RedirectResponse
    {
        $rate->update($request->validated());

        return back()->with('success', 'Agent rate updated successfully.');
    }

    public function destroy(AgentRate $rate): RedirectResponse
    {
        $rate->delete();

        return back()->with('success', 'Agent rate deleted successfully.');
    }
}
