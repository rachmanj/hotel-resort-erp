<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AgentRateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAgentTierRateRequest;
use App\Http\Requests\Admin\UpdateAgentTierRateRequest;
use App\Models\AgentTierRate;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentTierRateController extends Controller
{
    public function index(Request $request): Response
    {
        $rates = AgentTierRate::query()
            ->with(['roomType:id,name,code'])
            ->orderBy('rate_category')
            ->orderByDesc('valid_from')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AgentTierRate $rate) => [
                'id' => $rate->id,
                'rate_category' => $rate->rate_category?->value,
                'rate_category_label' => $rate->rate_category?->label(),
                'room_type' => $rate->roomType?->only(['id', 'name', 'code']),
                'nightly_rate' => $rate->nightly_rate,
                'valid_from' => $rate->valid_from?->toDateString(),
                'valid_to' => $rate->valid_to?->toDateString(),
                'is_active' => $rate->is_active,
            ]);

        return Inertia::render('Admin/AgentTierRates/Index', [
            'rates' => $rates,
            'roomTypes' => RoomType::query()->orderBy('name')->get(['id', 'name', 'code']),
            'tiers' => collect(AgentRateCategory::cases())->map(fn (AgentRateCategory $tier) => [
                'value' => $tier->value,
                'label' => $tier->label(),
            ]),
        ]);
    }

    public function store(StoreAgentTierRateRequest $request): RedirectResponse
    {
        AgentTierRate::query()->create($request->validated());

        return back()->with('success', 'Agent tier rate created successfully.');
    }

    public function update(UpdateAgentTierRateRequest $request, AgentTierRate $agentTierRate): RedirectResponse
    {
        $agentTierRate->update($request->validated());

        return back()->with('success', 'Agent tier rate updated successfully.');
    }

    public function destroy(AgentTierRate $agentTierRate): RedirectResponse
    {
        $agentTierRate->delete();

        return back()->with('success', 'Agent tier rate deleted successfully.');
    }
}
