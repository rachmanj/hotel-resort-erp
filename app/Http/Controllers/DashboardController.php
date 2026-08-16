<?php

namespace App\Http\Controllers;

use App\Enums\HousekeepingStatus;
use App\Enums\ReservationRoomStatus;
use App\Enums\RoomStatus;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Services\HousekeepingService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private HousekeepingService $housekeepingService,
    ) {}

    public function index(): Response
    {
        $hotelId = (int) session('current_hotel_id');

        $board = $this->housekeepingService->getRoomStatusBoard($hotelId);

        $occupiedRooms = ReservationRoom::query()
            ->where('status', ReservationRoomStatus::CheckedIn->value)
            ->whereHas('reservation', fn ($query) => $query->where('hotel_id', $hotelId))
            ->count();

        $sellableRooms = Room::query()
            ->where('hotel_id', $hotelId)
            ->whereNotIn('status', [
                RoomStatus::OutOfOrder->value,
                RoomStatus::OutOfService->value,
            ])
            ->count();

        $occupancy = $sellableRooms > 0
            ? round($occupiedRooms / $sellableRooms * 100, 1)
            : 0;

        $checkinsToday = ReservationRoom::query()
            ->where('status', ReservationRoomStatus::CheckedIn->value)
            ->whereDate('check_in_at', today())
            ->whereHas('reservation', fn ($query) => $query->where('hotel_id', $hotelId))
            ->count();

        return Inertia::render('Dashboard/Index', [
            'occupancy' => $occupancy,
            'checkinsToday' => $checkinsToday,
            'occupiedRooms' => $occupiedRooms,
            'sellableRooms' => $sellableRooms,
            'roomStatusSummary' => [
                'total' => $board->count(),
                'dirty' => $board->where('housekeeping_status', HousekeepingStatus::Dirty->value)->count(),
                'cleaning' => $board->where('housekeeping_status', HousekeepingStatus::Cleaning->value)->count(),
                'clean' => $board->where('housekeeping_status', HousekeepingStatus::Clean->value)->count(),
                'ready' => $board->where('housekeeping_status', HousekeepingStatus::Ready->value)->count(),
                'out_of_order' => $board->where('housekeeping_status', HousekeepingStatus::OutOfOrder->value)->count(),
            ],
            'rooms' => $board->map(fn (array $room) => [
                'id' => $room['id'],
                'number' => $room['number'],
                'housekeeping_status' => $room['housekeeping_status'],
                'housekeeping_status_label' => $room['housekeeping_status_label'],
                'housekeeping_status_color' => $room['housekeeping_status_color'],
            ])->values(),
        ]);
    }
}
