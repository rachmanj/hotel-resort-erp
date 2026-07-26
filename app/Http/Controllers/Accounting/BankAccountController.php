<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function index(): Response
    {
        $accounts = BankAccount::query()
            ->with('chartOfAccount:id,account_code,name')
            ->orderBy('bank_name')
            ->get()
            ->map(fn (BankAccount $account) => [
                'id' => $account->id,
                'bank_name' => $account->bank_name,
                'account_no' => $account->account_no,
                'account_name' => $account->account_name,
                'currency_code' => $account->currency_code,
                'gl_account' => $account->chartOfAccount
                    ? "{$account->chartOfAccount->account_code} - {$account->chartOfAccount->name}"
                    : null,
                'is_active' => $account->is_active,
            ]);

        return Inertia::render('Accounting/BankReconciliation/Accounts', [
            'bankAccounts' => $accounts,
            'glAccounts' => ChartOfAccount::query()
                ->where('is_postable', true)
                ->where('is_active', true)
                ->where('account_code', 'like', '1-1%')
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_no' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:100'],
            'chart_of_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        BankAccount::query()->create($validated);

        return back()->with('success', 'Bank account created.');
    }

    public function update(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:100'],
            'account_no' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:100'],
            'chart_of_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'currency_code' => ['required', 'string', 'size:3'],
            'is_active' => ['required', 'boolean'],
        ]);

        $bankAccount->update($validated);

        return back()->with('success', 'Bank account updated.');
    }
}
