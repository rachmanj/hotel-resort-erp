<?php

namespace App\Services\Reports;

use App\Enums\ReservationRoomStatus;
use App\Enums\RoomStatus;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OccupancyReport
{
    /**
     * @return array{
     *     summary: array{rooms_sold: int, rooms_available: int, occupancy_pct: float, total_rooms: int},
     *     by_room_type: Collection<int, array{
     *         room_type_id: int,
     *         room_type_name: string,
     *         total_rooms: int,
     *         rooms_sold: int,
     *         rooms_available: int,
     *         occupancy_pct: float
     *     }>
     * }
     */
    public function generate(int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        $days = (int) $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1;
        $roomTypes = RoomType::query()->orderBy('name')->get();

        $byRoomType = $roomTypes->map(function (RoomType $roomType) use ($hotelId, $startDate, $endDate, $days): array {
            $totalRooms = Room::query()
                ->where('hotel_id', $hotelId)
                ->where('room_type_id', $roomType->id)
                ->whereNotIn('status', [RoomStatus::OutOfOrder->value, RoomStatus::OutOfService->value])
                ->count();

            $roomsSold = $this->countRoomNights($hotelId, $startDate, $endDate, $roomType->id);
            $roomsAvailable = $totalRooms * $days;
            $occupancyPct = $roomsAvailable > 0 ? round(($roomsSold / $roomsAvailable) * 100, 2) : 0.0;

            return [
                'room_type_id' => $roomType->id,
                'room_type_name' => $roomType->name,
                'total_rooms' => $totalRooms,
                'rooms_sold' => $roomsSold,
                'rooms_available' => $roomsAvailable,
                'occupancy_pct' => $occupancyPct,
            ];
        })->filter(fn (array $row): bool => $row['total_rooms'] > 0)->values();

        $totalRooms = Room::query()
            ->where('hotel_id', $hotelId)
            ->whereNotIn('status', [RoomStatus::OutOfOrder->value, RoomStatus::OutOfService->value])
            ->count();

        $roomsSold = $this->countRoomNights($hotelId, $startDate, $endDate);
        $roomsAvailable = $totalRooms * $days;
        $occupancyPct = $roomsAvailable > 0 ? round(($roomsSold / $roomsAvailable) * 100, 2) : 0.0;

        return [
            'summary' => [
                'rooms_sold' => $roomsSold,
                'rooms_available' => $roomsAvailable,
                'occupancy_pct' => $occupancyPct,
                'total_rooms' => $totalRooms,
            ],
            'by_room_type' => $byRoomType,
        ];
    }

    private function countRoomNights(int $hotelId, Carbon $startDate, Carbon $endDate, ?int $roomTypeId = null): int
    {
        $nights = 0;
        $current = $startDate->copy()->startOfDay();
        $endDay = $endDate->copy()->startOfDay();

        while ($current <= $endDay) {
            $date = $current->toDateString();

            $query = ReservationRoom::query()
                ->join('reservations', 'reservation_rooms.reservation_id', '=', 'reservations.id')
                ->where('reservations.hotel_id', $hotelId)
                ->whereIn('reservation_rooms.status', [
                    ReservationRoomStatus::Booked->value,
                    ReservationRoomStatus::CheckedIn->value,
                    ReservationRoomStatus::CheckedOut->value,
                ])
                ->whereDate('reservations.arrival_date', '<=', $date)
                ->whereDate('reservations.departure_date', '>', $date);

            if ($roomTypeId !== null) {
                $query->where('reservation_rooms.room_type_id', $roomTypeId);
            }

            $nights += $query->count();
            $current->addDay();
        }

        return $nights;
    }
}
