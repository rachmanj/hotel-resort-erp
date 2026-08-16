<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AgentType;
use App\Enums\CommissionBasis;
use App\Enums\CommissionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAgentRequest;
use App\Http\Requests\Admin\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function index(Request $request): Response
    {
        $agents = Agent::query()
            ->with(['company:id,name', 'user:id,name,email'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('channel_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Agent $agent) => [
                'id' => $agent->id,
                'name' => $agent->name,
                'code' => $agent->code,
                'agent_type' => $agent->agent_type->value,
                'agent_type_label' => $agent->agent_type->label(),
                'channel_code' => $agent->channel_code,
                'contact_person' => $agent->contact_person,
                'phone' => $agent->phone,
                'email' => $agent->email,
                'commission_percent' => $agent->commission_percent,
                'commission_type' => $agent->commission_type->value,
                'commission_flat_amount' => $agent->commission_flat_amount,
                'commission_basis' => $agent->commission_basis->value,
                'commission_basis_label' => $agent->commission_basis->label(),
                'payment_terms_days' => $agent->payment_terms_days,
                'company' => $agent->company?->only(['id', 'name']),
                'user' => $agent->user?->only(['id', 'name', 'email']),
                'is_active' => $agent->is_active,
            ]);

        return Inertia::render('Admin/Agents/Index', [
            'agents' => $agents,
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'agentTypes' => collect(AgentType::cases())->map(fn (AgentType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'commissionBases' => collect(CommissionBasis::cases())->map(fn (CommissionBasis $b) => [
                'value' => $b->value,
                'label' => $b->label(),
            ]),
            'commissionTypes' => collect(CommissionType::cases())->map(fn (CommissionType $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $agent = Agent::query()->create($data);

        $this->syncPortalUser($agent, $data['user_id'] ?? null);

        return back()->with('success', 'Agent created successfully.');
    }

    public function update(UpdateAgentRequest $request, Agent $agent): RedirectResponse
    {
        $data = $request->validated();
        $agent->update($data);

        $this->syncPortalUser($agent, $data['user_id'] ?? null);

        return back()->with('success', 'Agent updated successfully.');
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        if ($agent->reservations()->exists()) {
            return back()->with('error', 'Cannot delete agent with existing reservations.');
        }

        if ($agent->user_id !== null) {
            User::query()->whereKey($agent->user_id)->update(['agent_id' => null]);
        }

        $agent->delete();

        return back()->with('success', 'Agent deleted successfully.');
    }

    private function syncPortalUser(Agent $agent, ?int $userId): void
    {
        if ($agent->user_id !== null && $agent->user_id !== $userId) {
            User::query()->whereKey($agent->user_id)->update(['agent_id' => null]);
        }

        if ($userId === null) {
            return;
        }

        User::query()->whereKey($userId)->update(['agent_id' => $agent->id]);

        $user = User::query()->find($userId);
        $user?->assignRole('agent');
    }
}
