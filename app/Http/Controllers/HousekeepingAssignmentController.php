<?php

namespace App\Http\Controllers;

use App\Enums\HousekeepingAssignmentStatus;
use App\Enums\HousekeepingShift;
use App\Http\Requests\StoreHousekeepingAssignmentRequest;
use App\Http\Requests\UpdateHousekeepingAssignmentRequest;
use App\Models\HousekeepingAssignment;
use App\Models\Room;
use App\Models\User;
use App\Services\HousekeepingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HousekeepingAssignmentController extends Controller
{
    public function __construct(
        private HousekeepingService $housekeepingService,
    ) {}

    public function index(Request $request): Response
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : Carbon::today();

        $assignments = HousekeepingAssignment::query()
            ->with(['room.roomType:id,name', 'housekeeper:id,name', 'assignedBy:id,name'])
            ->whereDate('assignment_date', $date)
            ->when(session('current_hotel_id'), function ($query): void {
                $query->whereHas('room', fn ($q) => $q->where('hotel_id', session('current_hotel_id')));
            })
            ->orderBy('shift')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (HousekeepingAssignment $assignment) => [
                'id' => $assignment->id,
                'room' => $assignment->room?->only(['id', 'number']),
                'room_type' => $assignment->room?->roomType?->name,
                'housekeeper' => $assignment->housekeeper?->only(['id', 'name']),
                'assignment_date' => $assignment->assignment_date->toDateString(),
                'shift' => $assignment->shift->value,
                'shift_label' => $assignment->shift->label(),
                'status' => $assignment->status->value,
                'status_label' => $assignment->status->label(),
                'assigned_by' => $assignment->assignedBy?->only(['id', 'name']),
            ]);

        $housekeepers = User::query()
            ->role('housekeeping')
            ->when(session('current_hotel_id'), function ($query): void {
                $hotelId = session('current_hotel_id');
                $query->where(function ($q) use ($hotelId): void {
                    $q->where('hotel_id', $hotelId)
                        ->orWhereHas('hotels', fn ($h) => $h->where('hotels.id', $hotelId));
                });
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $rooms = Room::query()
            ->with('roomType:id,name')
            ->orderBy('number')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'number' => $room->number,
                'room_type' => $room->roomType?->name,
                'status' => $room->status->value,
            ]);

        return Inertia::render('Housekeeping/Assignments', [
            'assignments' => $assignments,
            'housekeepers' => $housekeepers,
            'rooms' => $rooms,
            'shifts' => collect(HousekeepingShift::cases())->map(fn (HousekeepingShift $shift) => [
                'value' => $shift->value,
                'label' => $shift->label(),
            ]),
            'statuses' => collect(HousekeepingAssignmentStatus::cases())->map(fn (HousekeepingAssignmentStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
            'filters' => [
                'date' => $date->toDateString(),
            ],
        ]);
    }

    public function store(StoreHousekeepingAssignmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $housekeeper = User::query()->findOrFail($validated['housekeeper_id']);

        $this->housekeepingService->assignRooms(
            $housekeeper,
            $validated['room_ids'],
            Carbon::parse($validated['assignment_date']),
            $validated['shift'],
            $request->user(),
        );

        return redirect()
            ->route('housekeeping.assignments', ['date' => $validated['assignment_date']])
            ->with('success', 'Rooms assigned successfully.');
    }

    public function update(UpdateHousekeepingAssignmentRequest $request, HousekeepingAssignment $assignment): RedirectResponse
    {
        $assignment->update($request->validated());

        return redirect()
            ->back()
            ->with('success', 'Assignment updated.');
    }

    public function generate(Request $request): RedirectResponse
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->string('date')->toString())
            : Carbon::today();

        $assignments = $this->housekeepingService->generateDailyAssignments(
            session('current_hotel_id'),
            $date,
        );

        return redirect()
            ->route('housekeeping.assignments', ['date' => $date->toDateString()])
            ->with('success', "Generated {$assignments->count()} assignment(s).");
    }
}
