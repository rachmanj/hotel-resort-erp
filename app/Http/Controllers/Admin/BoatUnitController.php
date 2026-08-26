<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBoatUnitRequest;
use App\Http\Requests\Admin\UpdateBoatUnitRequest;
use App\Models\BoatUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BoatUnitController extends Controller
{
    public function index(Request $request): Response
    {
        $boatUnits = BoatUnit::query()
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
            ->through(fn (BoatUnit $unit) => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'capacity' => $unit->capacity,
                'engine_hp' => $unit->engine_hp,
                'is_own' => $unit->is_own,
                'is_active' => $unit->is_active,
            ]);

        return Inertia::render('Admin/BoatUnits/Index', [
            'boatUnits' => $boatUnits,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreBoatUnitRequest $request): RedirectResponse
    {
        BoatUnit::query()->create($request->validated());

        return back()->with('success', 'Boat unit created successfully.');
    }

    public function update(UpdateBoatUnitRequest $request, BoatUnit $boatUnit): RedirectResponse
    {
        $boatUnit->update($request->validated());

        return back()->with('success', 'Boat unit updated successfully.');
    }

    public function destroy(BoatUnit $boatUnit): RedirectResponse
    {
        if ($boatUnit->boatCharters()->exists()) {
            return back()->with('error', 'Cannot delete boat unit with existing boat charters.');
        }

        $boatUnit->delete();

        return back()->with('success', 'Boat unit deleted successfully.');
    }
}
