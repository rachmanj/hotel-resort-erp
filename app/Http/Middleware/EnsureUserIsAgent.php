<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->can('agents.portal')) {
            abort(403, 'Agent portal access required.');
        }

        $agent = Agent::query()->where('user_id', $user->id)->first()
            ?? ($user->agent_id !== null ? Agent::query()->find($user->agent_id) : null);

        if ($agent === null || ! $agent->is_active) {
            abort(403, 'No active agent profile linked to this account.');
        }

        $request->attributes->set('agent', $agent);

        return $next($request);
    }
}
