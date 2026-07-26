<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTaxRuleRequest;
use App\Models\TaxRule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TaxRuleController extends Controller
{
    public function index(): Response
    {
        $taxRules = TaxRule::query()
            ->orderBy('order')
            ->get()
            ->map(fn (TaxRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'code' => $rule->code,
                'rate_percent' => $rule->rate_percent,
                'applies_to' => $rule->applies_to,
                'is_compounding' => $rule->is_compounding,
                'is_active' => $rule->is_active,
                'order' => $rule->order,
            ]);

        return Inertia::render('Admin/TaxRules/Index', [
            'taxRules' => $taxRules,
        ]);
    }

    public function update(UpdateTaxRuleRequest $request, TaxRule $taxRule): RedirectResponse
    {
        $taxRule->update($request->validated());

        return back()->with('success', 'Tax rule updated successfully.');
    }
}
