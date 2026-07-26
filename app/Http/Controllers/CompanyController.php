<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        $companies = Company::query()
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request): void {
                $search = $request->string('search')->toString();
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('tax_id', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Company $company) => [
                'id' => $company->id,
                'name' => $company->name,
                'tax_id' => $company->tax_id,
                'phone' => $company->phone,
                'email' => $company->email,
                'credit_limit' => $company->credit_limit,
                'payment_terms_days' => $company->payment_terms_days,
                'is_active' => $company->is_active,
            ]);

        return Inertia::render('Companies/Index', [
            'companies' => $companies,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Companies/Create');
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $company = Company::query()->create($request->validated());

        return redirect()
            ->route('companies.edit', $company)
            ->with('success', 'Company created successfully.');
    }

    public function show(Company $company): Response
    {
        return Inertia::render('Companies/Edit', [
            'company' => $company->only([
                'id', 'name', 'tax_id', 'billing_address', 'phone', 'email',
                'credit_limit', 'payment_terms_days', 'is_active',
            ]),
        ]);
    }

    public function edit(Company $company): Response
    {
        return Inertia::render('Companies/Edit', [
            'company' => $company->only([
                'id', 'name', 'tax_id', 'billing_address', 'phone', 'email',
                'credit_limit', 'payment_terms_days', 'is_active',
            ]),
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update($request->validated());

        return back()->with('success', 'Company updated successfully.');
    }
}
