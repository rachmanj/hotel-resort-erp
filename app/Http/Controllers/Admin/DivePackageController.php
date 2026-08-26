<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DivePackageType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDivePackageRequest;
use App\Http\Requests\Admin\UpdateDivePackageRequest;
use App\Models\DivePackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DivePackageController extends Controller
{
    public function index(Request $request): Response
    {
        $divePackages = DivePackage::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DivePackage $package) => [
                'id' => $package->id,
                'code' => $package->code,
                'name' => $package->name,
                'type' => $package->type->value,
                'type_label' => $package->type->label(),
                'price_per_person' => (float) $package->price_per_person,
                'min_pax' => $package->min_pax,
                'includes' => $package->includes,
                'is_active' => $package->is_active,
            ]);

        return Inertia::render('Admin/DivePackages/Index', [
            'divePackages' => $divePackages,
            'packageTypes' => collect(DivePackageType::cases())->map(fn (DivePackageType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreDivePackageRequest $request): RedirectResponse
    {
        DivePackage::query()->create($request->validated());

        return back()->with('success', 'Dive package created successfully.');
    }

    public function update(UpdateDivePackageRequest $request, DivePackage $divePackage): RedirectResponse
    {
        $divePackage->update($request->validated());

        return back()->with('success', 'Dive package updated successfully.');
    }

    public function destroy(DivePackage $divePackage): RedirectResponse
    {
        if ($divePackage->boatCharters()->exists()) {
            return back()->with('error', 'Cannot delete dive package with existing boat charters.');
        }

        $divePackage->delete();

        return back()->with('success', 'Dive package deleted successfully.');
    }
}
