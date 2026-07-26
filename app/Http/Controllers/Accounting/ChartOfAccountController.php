<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChartOfAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = ChartOfAccount::query()
            ->when($request->filled('account_type'), fn ($q) => $q->where('account_type', $request->string('account_type')))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = $request->string('search')->toString();
                $q->where(function ($query) use ($search): void {
                    $query->where('account_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->orderBy('account_code')
            ->get()
            ->map(fn (ChartOfAccount $account) => [
                'id' => $account->id,
                'parent_id' => $account->parent_id,
                'account_code' => $account->account_code,
                'name' => $account->name,
                'account_type' => $account->account_type->value,
                'account_type_label' => $account->account_type->label(),
                'normal_balance' => $account->normal_balance->value,
                'is_postable' => $account->is_postable,
                'is_active' => $account->is_active,
            ]);

        return Inertia::render('Accounting/ChartOfAccounts/Index', [
            'accounts' => $accounts,
            'filters' => $request->only(['search', 'account_type']),
            'accountTypeOptions' => collect(AccountType::cases())->map(fn (AccountType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'normalBalanceOptions' => collect(NormalBalance::cases())->map(fn (NormalBalance $balance) => [
                'value' => $balance->value,
                'label' => $balance->label(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'account_code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'account_type' => ['required', 'string'],
            'normal_balance' => ['required', 'string'],
            'is_postable' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        ChartOfAccount::query()->create($validated);

        return back()->with('success', 'Account created.');
    }

    public function update(Request $request, ChartOfAccount $chartOfAccount): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'is_active' => ['boolean'],
        ]);

        $chartOfAccount->update($validated);

        return back()->with('success', 'Account updated.');
    }
}
