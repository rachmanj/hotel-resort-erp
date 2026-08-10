<?php

namespace App\Http\Controllers;

use App\Enums\ReservationRoomStatus;
use App\Enums\RoomStatus;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Models\Floor;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    public function index(Request $request): Response
    {
        $rooms = Room::query()
            ->with(['roomType:id,name,code', 'floor:id,name,level'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where('number', 'like', "%{$search}%");
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('number')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Room $room) => [
                'id' => $room->id,
                'number' => $room->number,
                'status' => $room->status->value,
                'status_label' => $room->status->label(),
                'status_color' => $room->status->color(),
                'room_type_id' => $room->room_type_id,
                'floor_id' => $room->floor_id,
                'room_type' => $room->roomType?->only(['id', 'name', 'code']),
                'floor' => $room->floor?->only(['id', 'name', 'level']),
                'notes' => $room->notes,
            ]);

        return Inertia::render('Rooms/Index', [
            'rooms' => $rooms,
            'roomTypes' => $this->roomTypes(),
            'floors' => $this->floors(),
            'statuses' => collect(RoomStatus::cases())->map(fn (RoomStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ]),
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Rooms/Create', [
            'roomTypes' => $this->roomTypes(),
            'floors' => $this->floors(),
        ]);
    }

    public function store(StoreRoomRequest $request): RedirectResponse
    {
        $hotelId = session('current_hotel_id');

        if ($hotelId === null) {
            return back()->with('error', 'No hotel context selected.');
        }

        Room::query()->create([
            ...$request->validated(),
            'hotel_id' => $hotelId,
            'status' => RoomStatus::VacantClean->value,
        ]);

        return back()->with('success', 'Room created successfully.');
    }

    public function show(Room $room): Response
    {
        $room->load(['roomType', 'floor', 'hotel']);

        return Inertia::render('Rooms/Show', [
            'room' => [
                'id' => $room->id,
                'number' => $room->number,
                'status' => $room->status->value,
                'status_label' => $room->status->label(),
                'status_color' => $room->status->color(),
                'notes' => $room->notes,
                'room_type' => $room->roomType?->only(['id', 'name', 'code', 'base_rate', 'max_occupancy']),
                'floor' => $room->floor?->only(['id', 'name', 'level']),
                'hotel' => $room->hotel?->only(['id', 'name', 'code']),
            ],
        ]);
    }

    public function edit(Room $room): Response
    {
        return Inertia::render('Rooms/Edit', [
            'room' => [
                'id' => $room->id,
                'number' => $room->number,
                'room_type_id' => $room->room_type_id,
                'floor_id' => $room->floor_id,
                'notes' => $room->notes,
            ],
            'roomTypes' => $this->roomTypes(),
            'floors' => $this->floors(),
        ]);
    }

    public function update(UpdateRoomRequest $request, Room $room): RedirectResponse
    {
        $room->update($request->validated());

        return back()->with('success', 'Room updated successfully.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        $hasActiveReservations = ReservationRoom::query()
            ->where('room_id', $room->id)
            ->whereIn('status', [
                ReservationRoomStatus::Booked->value,
                ReservationRoomStatus::CheckedIn->value,
            ])
            ->exists();

        if ($hasActiveReservations) {
            return back()->with('error', 'Cannot delete room with active reservations.');
        }

        $room->delete();

        return back()->with('success', 'Room deleted successfully.');
    }

    /**
     * @return Collection<int, array{id: int, name: string, code: string}>
     */
    private function roomTypes()
    {
        return RoomType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /**
     * @return Collection<int, array{id: int, name: string, level: int}>
     */
    private function floors()
    {
        return Floor::query()
            ->orderBy('name')
            ->get(['id', 'name', 'level']);
    }
}
