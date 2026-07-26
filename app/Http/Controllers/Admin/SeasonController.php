<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSeasonRequest;
use App\Http\Requests\Admin\UpdateSeasonRequest;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SeasonController extends Controller
{
    public function index(Request $request): Response
    {
        $seasons = Season::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('start_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Seasons/Index', [
            'seasons' => $seasons,
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(StoreSeasonRequest $request): RedirectResponse
    {
        Season::query()->create($request->validated());

        return back()->with('success', 'Season created successfully.');
    }

    public function update(UpdateSeasonRequest $request, Season $season): RedirectResponse
    {
        $season->update($request->validated());

        return back()->with('success', 'Season updated successfully.');
    }

    public function destroy(Season $season): RedirectResponse
    {
        $season->delete();

        return back()->with('success', 'Season deleted successfully.');
    }
}
