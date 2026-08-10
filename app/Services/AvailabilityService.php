<?php

namespace App\Services;

use App\Enums\ReservationRoomStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AvailabilityService
{
    /**
     * @var list<string>
     */
    private const BLOCKING_STATUSES = [
        ReservationRoomStatus::Booked->value,
        ReservationRoomStatus::CheckedIn->value,
    ];

    public function isAvailable(Room $room, Carbon $checkin, Carbon $checkout, ?int $excludeReservationId = null): bool
    {
        return ! $this->queryOverlappingReservationRooms($checkin, $checkout, $room->id, $excludeReservationId)->exists();
    }

    public function assertRoomAvailable(Room $room, Carbon $checkin, Carbon $checkout, ?int $excludeReservationId = null): void
    {
        if (! $this->isAvailable($room, $checkin, $checkout, $excludeReservationId)) {
            throw new RoomNotAvailableException;
        }
    }

    public function lockOverlappingForHotel(int $hotelId, Carbon $checkin, Carbon $checkout): void
    {
        $this->queryOverlappingReservationRooms($checkin, $checkout)
            ->whereHas('reservation', fn ($query) => $query->where('hotel_id', $hotelId))
            ->lockForUpdate()
            ->get();
    }

    /**
     * @return Collection<int, Room>
     */
    public function getAvailableRooms(int $roomTypeId, Carbon $checkin, Carbon $checkout, ?int $hotelId = null, ?int $excludeReservationId = null): Collection
    {
        $hotelId ??= session('current_hotel_id');

        $occupiedRoomIds = $this->queryOverlappingReservationRooms($checkin, $checkout, null, $excludeReservationId)
            ->when($hotelId !== null, function ($query) use ($hotelId): void {
                $query->whereHas('reservation', fn ($q) => $q->where('hotel_id', $hotelId));
            })
            ->pluck('room_id')
            ->filter()
            ->unique()
            ->values();

        return Room::query()
            ->where('room_type_id', $roomTypeId)
            ->when($hotelId !== null, fn ($q) => $q->where('hotel_id', $hotelId))
            ->when($occupiedRoomIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $occupiedRoomIds))
            ->orderBy('number')
            ->get();
    }

    /**
     * @return array<int, array{room_type_id: int, name: string, code: string, available_count: int, total_count: int}>
     */
    public function getAvailability(Carbon $checkin, Carbon $checkout, ?int $hotelId = null, ?int $excludeReservationId = null): array
    {
        $hotelId ??= session('current_hotel_id');

        $roomTypes = RoomType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $result = [];

        foreach ($roomTypes as $roomType) {
            $totalCount = Room::query()
                ->where('room_type_id', $roomType->id)
                ->when($hotelId !== null, fn ($q) => $q->where('hotel_id', $hotelId))
                ->count();

            $availableRooms = $this->getAvailableRooms($roomType->id, $checkin, $checkout, $hotelId, $excludeReservationId);

            $result[] = [
                'room_type_id' => $roomType->id,
                'name' => $roomType->name,
                'code' => $roomType->code,
                'available_count' => $availableRooms->count(),
                'total_count' => $totalCount,
            ];
        }

        return $result;
    }

    /**
     * @return Builder<ReservationRoom>
     */
    private function queryOverlappingReservationRooms(Carbon $checkin, Carbon $checkout, ?int $roomId = null, ?int $excludeReservationId = null)
    {
        $checkinDate = $checkin->toDateString();
        $checkoutDate = $checkout->toDateString();

        return ReservationRoom::query()
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->when($roomId !== null, fn ($q) => $q->where('room_id', $roomId))
            ->when($excludeReservationId !== null, fn ($q) => $q->where('reservation_id', '!=', $excludeReservationId))
            ->whereNotNull('room_id')
            ->whereHas('reservation', function ($query) use ($checkinDate, $checkoutDate): void {
                $query->where('arrival_date', '<', $checkoutDate)
                    ->where('departure_date', '>', $checkinDate)
                    ->whereNotIn('status', ['cancelled', 'no_show']);
            });
    }
}
