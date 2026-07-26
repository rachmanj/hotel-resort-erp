<?php

namespace App\Http\Controllers;

use App\Enums\RoomStatus;
use App\Models\Room;
use Illuminate\Http\Request;
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
                'room_type' => $room->roomType?->only(['id', 'name', 'code']),
                'floor' => $room->floor?->only(['id', 'name', 'level']),
                'notes' => $room->notes,
            ]);

        return Inertia::render('Rooms/Index', [
            'rooms' => $rooms,
            'statuses' => collect(RoomStatus::cases())->map(fn (RoomStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ]),
            'filters' => $request->only(['search', 'status']),
        ]);
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
}
