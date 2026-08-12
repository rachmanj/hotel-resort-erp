<?php

namespace App\Http\Controllers\AgentPortal;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');

        $bookings = Reservation::query()
            ->where('agent_id', $agent->id)
            ->with(['guest:id,full_name,phone', 'reservationRooms.room:id,number', 'reservationRooms.roomType:id,name'])
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderByDesc('arrival_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'reservation_code' => $reservation->reservation_code,
                'guest_name' => $reservation->guest?->full_name,
                'guest_phone' => $reservation->guest?->phone,
                'status' => $reservation->status->value,
                'status_label' => $reservation->status->label(),
                'arrival_date' => $reservation->arrival_date?->toDateString(),
                'departure_date' => $reservation->departure_date?->toDateString(),
                'room_number' => $reservation->reservationRooms->first()?->room?->number,
                'room_type' => $reservation->reservationRooms->first()?->roomType?->name,
            ]);

        return Inertia::render('AgentPortal/Bookings/Index', [
            'bookings' => $bookings,
            'agent' => $agent->only(['id', 'name', 'code']),
            'filters' => $request->only(['status']),
        ]);
    }
}
