<?php

namespace App\Http\Controllers;

use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReservationCalendarController extends Controller
{
    private const DEFAULT_DAYS = 14;
    private const MIN_DAYS = 7;
    private const MAX_DAYS = 62;

    public function index(Request $request): Response
    {
        $hotelId = (int) session('current_hotel_id');

        $startDate = $request->string('start_date')->toString() ?: now()->startOfWeek()->toDateString();
        $endDate = $request->string('end_date')->toString();

        $start = Carbon::parse($startDate)->startOfDay();

        if ($endDate !== '') {
            $end = Carbon::parse($endDate)->startOfDay();

            if ($end->lt($start)) {
                $end = $start->copy()->addDays(self::DEFAULT_DAYS - 1);
            }

            $days = min($start->diffInDays($end) + 1, self::MAX_DAYS);
        } else {
            $days = (int) $request->input('days', self::DEFAULT_DAYS);
            $days = max(self::MIN_DAYS, min($days, self::MAX_DAYS));
        }

        $end = $start->copy()->addDays($days);

        $rooms = Room::query()
            ->where('hotel_id', $hotelId)
            ->with(['roomType:id,name,code', 'floor:id,name'])
            ->orderBy('number')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'number' => $room->number,
                'room_type' => $room->roomType?->only(['id', 'name', 'code']),
                'floor' => $room->floor?->only(['id', 'name']),
            ]);

        $reservations = Reservation::query()
            ->where('hotel_id', $hotelId)
            ->with(['guest:id,full_name', 'reservationRooms.room:id,number'])
            ->where('arrival_date', '<', $end->toDateString())
            ->where('departure_date', '>', $start->toDateString())
            ->whereNotIn('status', [ReservationStatus::Cancelled->value, ReservationStatus::NoShow->value])
            ->get()
            ->flatMap(function (Reservation $reservation) {
                return $reservation->reservationRooms
                    ->filter(fn ($rr) => $rr->room_id !== null
                        && in_array($rr->status, [
                            ReservationRoomStatus::Booked,
                            ReservationRoomStatus::CheckedIn,
                        ], true))
                    ->map(fn ($rr) => [
                        'id' => $reservation->id,
                        'reservation_code' => $reservation->reservation_code,
                        'room_id' => $rr->room_id,
                        'guest_name' => $reservation->guest?->full_name,
                        'status' => $reservation->status->value,
                        'status_color' => $reservation->status->color(),
                        'arrival_date' => $reservation->arrival_date->toDateString(),
                        'departure_date' => $reservation->departure_date->toDateString(),
                    ]);
            })
            ->values();

        $dateColumns = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $dateColumns[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('D, M j'),
            ];
        }

        return Inertia::render('Reservations/Calendar', [
            'rooms' => $rooms,
            'reservations' => $reservations,
            'dateColumns' => $dateColumns,
            'startDate' => $start->toDateString(),
            'endDate' => $end->copy()->subDay()->toDateString(),
            'days' => $days,
        ]);
    }
}
