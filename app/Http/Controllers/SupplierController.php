<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $suppliers = Supplier::query()
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = $request->string('search')->toString();
                $q->where('name', 'like', "%{$search}%");
            })
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_person' => $supplier->contact_person,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'is_active' => $supplier->is_active,
            ]);

        return Inertia::render('Purchasing/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'is_active']),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        Supplier::query()->create($request->validated());

        return back()->with('success', 'Supplier created.');
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return back()->with('success', 'Supplier updated.');
    }
}
