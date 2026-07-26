<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSpaTherapistRequest;
use App\Http\Requests\StoreSpaTherapistScheduleRequest;
use App\Http\Requests\UpdateSpaTherapistRequest;
use App\Models\SpaTherapist;
use App\Models\SpaTherapistSchedule;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SpaTherapistController extends Controller
{
    public function index(): Response
    {
        $therapists = SpaTherapist::query()
            ->with('user:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (SpaTherapist $therapist) => [
                'id' => $therapist->id,
                'name' => $therapist->name,
                'phone' => $therapist->phone,
                'user' => $therapist->user?->only(['id', 'name']),
            ]);

        return Inertia::render('Spa/Therapists/Index', [
            'therapists' => $therapists,
            'userOptions' => User::query()->role('spa')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function schedules(Request $request): Response
    {
        $therapistId = $request->integer('therapist_id') ?: null;
        $workDate = $request->string('work_date')->toString() ?: now()->toDateString();

        $schedules = SpaTherapistSchedule::query()
            ->with('therapist:id,name')
            ->when($therapistId, fn ($q) => $q->where('spa_therapist_id', $therapistId))
            ->when($workDate, fn ($q) => $q->whereDate('work_date', $workDate))
            ->orderBy('start_time')
            ->get()
            ->map(fn (SpaTherapistSchedule $schedule) => [
                'id' => $schedule->id,
                'spa_therapist_id' => $schedule->spa_therapist_id,
                'therapist_name' => $schedule->therapist?->name,
                'work_date' => $schedule->work_date?->toDateString(),
                'start_time' => substr((string) $schedule->start_time, 0, 5),
                'end_time' => substr((string) $schedule->end_time, 0, 5),
            ]);

        return Inertia::render('Spa/Therapists/Schedules', [
            'schedules' => $schedules,
            'therapists' => SpaTherapist::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'therapist_id' => $therapistId,
                'work_date' => $workDate,
            ],
        ]);
    }

    public function store(StoreSpaTherapistRequest $request): RedirectResponse
    {
        SpaTherapist::query()->create($request->validated());

        return back()->with('success', 'Therapist created.');
    }

    public function update(UpdateSpaTherapistRequest $request, SpaTherapist $spaTherapist): RedirectResponse
    {
        $spaTherapist->update($request->validated());

        return back()->with('success', 'Therapist updated.');
    }

    public function destroy(SpaTherapist $spaTherapist): RedirectResponse
    {
        if ($spaTherapist->appointments()->exists()) {
            return back()->with('error', 'Cannot delete therapist with existing appointments.');
        }

        $spaTherapist->schedules()->delete();
        $spaTherapist->delete();

        return back()->with('success', 'Therapist deleted.');
    }

    public function storeSchedule(StoreSpaTherapistScheduleRequest $request): RedirectResponse
    {
        SpaTherapistSchedule::query()->create($request->validated());

        return back()->with('success', 'Schedule added.');
    }

    public function destroySchedule(SpaTherapistSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return back()->with('success', 'Schedule removed.');
    }
}
