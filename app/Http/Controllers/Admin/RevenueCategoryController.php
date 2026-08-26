<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRevenueCategoryRequest;
use App\Http\Requests\Admin\UpdateRevenueCategoryRequest;
use App\Models\RevenueCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RevenueCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $revenueCategories = RevenueCategory::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('coa_account_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (RevenueCategory $category) => [
                'id' => $category->id,
                'code' => $category->code,
                'name' => $category->name,
                'coa_account_code' => $category->coa_account_code,
                'sort_order' => $category->sort_order,
                'is_active' => $category->is_active,
            ]);

        return Inertia::render('Admin/RevenueCategories/Index', [
            'revenueCategories' => $revenueCategories,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreRevenueCategoryRequest $request): RedirectResponse
    {
        RevenueCategory::query()->create($request->validated());

        return back()->with('success', 'Revenue category created successfully.');
    }

    public function update(UpdateRevenueCategoryRequest $request, RevenueCategory $revenueCategory): RedirectResponse
    {
        $revenueCategory->update($request->validated());

        return back()->with('success', 'Revenue category updated successfully.');
    }

    public function destroy(RevenueCategory $revenueCategory): RedirectResponse
    {
        if ($revenueCategory->folioItems()->exists()) {
            return back()->with('error', 'Cannot delete revenue category with existing folio items.');
        }

        $revenueCategory->delete();

        return back()->with('success', 'Revenue category deleted successfully.');
    }
}
