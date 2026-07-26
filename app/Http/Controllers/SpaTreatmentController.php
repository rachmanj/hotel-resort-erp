<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpaTreatmentRequest;
use App\Http\Requests\UpdateSpaTreatmentRequest;
use App\Models\SpaTreatment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SpaTreatmentController extends Controller
{
    public function index(): Response
    {
        $treatments = SpaTreatment::query()
            ->orderBy('name')
            ->get()
            ->map(fn (SpaTreatment $treatment) => [
                'id' => $treatment->id,
                'name' => $treatment->name,
                'duration_minutes' => $treatment->duration_minutes,
                'price' => (float) $treatment->price,
                'description' => $treatment->description,
            ]);

        return Inertia::render('Spa/Treatments/Index', [
            'treatments' => $treatments,
        ]);
    }

    public function store(StoreSpaTreatmentRequest $request): RedirectResponse
    {
        SpaTreatment::query()->create($request->validated());

        return back()->with('success', 'Treatment created.');
    }

    public function update(UpdateSpaTreatmentRequest $request, SpaTreatment $spaTreatment): RedirectResponse
    {
        $spaTreatment->update($request->validated());

        return back()->with('success', 'Treatment updated.');
    }

    public function destroy(SpaTreatment $spaTreatment): RedirectResponse
    {
        if ($spaTreatment->appointments()->exists()) {
            return back()->with('error', 'Cannot delete treatment with existing appointments.');
        }

        $spaTreatment->delete();

        return back()->with('success', 'Treatment deleted.');
    }
}
