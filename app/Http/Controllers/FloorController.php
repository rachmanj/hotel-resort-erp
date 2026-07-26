<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFloorRequest;
use App\Http\Requests\UpdateFloorRequest;
use App\Models\Floor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FloorController extends Controller
{
    public function index(Request $request): Response
    {
        $floors = Floor::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('level')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Floors/Index', [
            'floors' => $floors,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreFloorRequest $request): RedirectResponse
    {
        Floor::query()->create($request->validated());

        return back()->with('success', 'Floor created successfully.');
    }

    public function update(UpdateFloorRequest $request, Floor $floor): RedirectResponse
    {
        $floor->update($request->validated());

        return back()->with('success', 'Floor updated successfully.');
    }

    public function destroy(Floor $floor): RedirectResponse
    {
        $floor->delete();

        return back()->with('success', 'Floor deleted successfully.');
    }
}
