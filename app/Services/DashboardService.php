<?php

namespace App\Services;

use App\Enums\FolioStatus;
use App\Enums\HousekeepingStatus;
use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\FolioItem;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use Carbon\Carbon;

class DashboardService
{
    public function __construct(
        private HousekeepingService $housekeepingService,
    ) {}

    /**
     * @return list<array{
     *     id: int,
     *     time: ?string,
     *     guest_name: string,
     *     room_label: string,
     *     nights: int,
     *     guest_count: int,
     *     source_label: string,
     *     agent_name: ?string,
     *     status_label: string,
     *     status_kind: string,
     *     folio_open: bool
     * }>
     */
    public function getArrivalsToday(int $hotelId): array
    {
        return $this->getMovementToday($hotelId, 'arrival');
    }

    /**
     * @return list<array{
     *     id: int,
     *     time: ?string,
     *     guest_name: string,
     *     room_label: string,
     *     nights: int,
     *     guest_count: int,
     *     source_label: string,
     *     agent_name: ?string,
     *     status_label: string,
     *     status_kind: string,
     *     folio_open: bool
     * }>
     */
    public function getDeparturesToday(int $hotelId): array
    {
        return $this->getMovementToday($hotelId, 'departure');
    }

    /**
     * @return list<array{date: string, label: string, occupancy: float}>
     */
    public function getOccupancySeries(int $hotelId): array
    {
        $sellableRooms = $this->countSellableRooms($hotelId);
        $series = [];

        for ($day = today()->subDays(13); $day <= today(); $day = $day->copy()->addDay()) {
            $occupiedRooms = $this->countOccupiedRoomsOnDate($hotelId, $day);
            $occupancy = $sellableRooms > 0
                ? round($occupiedRooms / $sellableRooms * 100, 1)
                : 0.0;

            $series[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('j M'),
                'occupancy' => $occupancy,
            ];
        }

        return $series;
    }

    public function getOccupancyDelta(int $hotelId): float
    {
        $series = $this->getOccupancySeries($hotelId);

        if (count($series) < 2) {
            return 0.0;
        }

        $todayOccupancy = $series[count($series) - 1]['occupancy'];
        $yesterdayOccupancy = $series[count($series) - 2]['occupancy'];

        return round($todayOccupancy - $yesterdayOccupancy, 1);
    }

    public function getInHouseGuests(int $hotelId): int
    {
        return (int) Reservation::query()
            ->where('hotel_id', $hotelId)
            ->where('status', ReservationStatus::CheckedIn->value)
            ->get(['adults', 'children'])
            ->sum(fn (Reservation $reservation): int => $reservation->adults + $reservation->children);
    }

    /**
     * @return list<array{category: string, amount: float, share: float}>
     */
    public function getRevenueMix(int $hotelId): array
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $rows = FolioItem::query()
            ->join('folios', 'folio_items.folio_id', '=', 'folios.id')
            ->join('revenue_categories', 'folio_items.revenue_category_id', '=', 'revenue_categories.id')
            ->where('folios.hotel_id', $hotelId)
            ->whereBetween('folio_items.posted_at', [$startOfMonth, $endOfMonth])
            ->groupBy('revenue_categories.id', 'revenue_categories.name')
            ->selectRaw('revenue_categories.name as category, SUM(folio_items.amount) as amount')
            ->orderByDesc('amount')
            ->get();

        $total = (float) $rows->sum('amount');

        if ($total <= 0) {
            return [];
        }

        return $rows->map(function (object $row) use ($total): array {
            $amount = (float) $row->amount;

            return [
                'category' => $row->category,
                'amount' => $amount,
                'share' => round($amount / $total * 100, 1),
            ];
        })->values()->all();
    }

    /**
     * @return list<array{
     *     id: int,
     *     time: ?string,
     *     guest_name: string,
     *     room_label: string,
     *     nights: int,
     *     guest_count: int,
     *     source_label: string,
     *     agent_name: ?string,
     *     status_label: string,
     *     status_kind: string,
     *     folio_open: bool
     * }>
     */
    private function getMovementToday(int $hotelId, string $direction): array
    {
        $dateColumn = $direction === 'arrival' ? 'arrival_date' : 'departure_date';
        $timeColumn = $direction === 'arrival' ? 'check_in_at' : 'check_out_at';

        $reservationRooms = ReservationRoom::query()
            ->with([
                'reservation.guest',
                'reservation.agent',
                'reservation.folios',
                'room',
                'roomType',
            ])
            ->whereHas('reservation', function ($query) use ($hotelId, $dateColumn): void {
                $query->where('hotel_id', $hotelId)
                    ->whereDate($dateColumn, today())
                    ->where('status', '!=', ReservationStatus::Cancelled->value);
            })
            ->orderByRaw("CASE WHEN {$timeColumn} IS NULL THEN 1 ELSE 0 END")
            ->orderBy($timeColumn)
            ->get();

        return $reservationRooms
            ->map(fn (ReservationRoom $reservationRoom): array => $this->formatMovementEntry(
                $reservationRoom,
                $direction,
                $timeColumn,
            ))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     time: ?string,
     *     guest_name: string,
     *     room_label: string,
     *     nights: int,
     *     guest_count: int,
     *     source_label: string,
     *     agent_name: ?string,
     *     status_label: string,
     *     status_kind: string,
     *     folio_open: bool
     * }
     */
    private function formatMovementEntry(
        ReservationRoom $reservationRoom,
        string $direction,
        string $timeColumn,
    ): array {
        $reservation = $reservationRoom->reservation;
        $timestamp = $reservationRoom->{$timeColumn};

        return [
            'id' => $reservationRoom->id,
            'time' => $timestamp?->format('H:i'),
            'guest_name' => $reservation->guest?->full_name ?? 'Unknown Guest',
            'room_label' => $this->formatRoomLabel($reservationRoom),
            'nights' => max(1, $reservation->arrival_date->diffInDays($reservation->departure_date)),
            'guest_count' => $reservation->adults + $reservation->children,
            'source_label' => $reservation->source->label(),
            'agent_name' => $reservation->agent?->name,
            'status_label' => $reservationRoom->status->label(),
            'status_kind' => $this->resolveStatusKind($reservationRoom, $direction),
            'folio_open' => $reservation->folios->contains(
                fn ($folio): bool => $folio->status === FolioStatus::Open,
            ),
        ];
    }

    private function formatRoomLabel(ReservationRoom $reservationRoom): string
    {
        $roomTypeName = $reservationRoom->roomType?->name ?? 'Unassigned';

        if ($reservationRoom->room === null) {
            return $roomTypeName;
        }

        return "{$roomTypeName} {$reservationRoom->room->number}";
    }

    private function resolveStatusKind(ReservationRoom $reservationRoom, string $direction): string
    {
        if ($direction === 'arrival') {
            return $this->resolveArrivalStatusKind($reservationRoom);
        }

        return $this->resolveDepartureStatusKind($reservationRoom);
    }

    private function resolveArrivalStatusKind(ReservationRoom $reservationRoom): string
    {
        if (in_array($reservationRoom->status, [ReservationRoomStatus::CheckedIn, ReservationRoomStatus::CheckedOut], true)) {
            return 'done';
        }

        if ($this->isRoomReadyForArrival($reservationRoom)) {
            return 'ready';
        }

        return 'waiting';
    }

    private function resolveDepartureStatusKind(ReservationRoom $reservationRoom): string
    {
        if ($reservationRoom->status === ReservationRoomStatus::CheckedOut) {
            return 'done';
        }

        $folioOpen = $reservationRoom->reservation->folios->contains(
            fn ($folio): bool => $folio->status === FolioStatus::Open,
        );

        return $folioOpen ? 'waiting' : 'ready';
    }

    private function isRoomReadyForArrival(ReservationRoom $reservationRoom): bool
    {
        if ($reservationRoom->room === null) {
            return false;
        }

        $housekeepingStatus = $this->housekeepingService->resolveHousekeepingStatus($reservationRoom->room);

        return in_array($housekeepingStatus, [HousekeepingStatus::Ready, HousekeepingStatus::Clean, HousekeepingStatus::Inspected], true)
            || in_array($reservationRoom->room->status, [RoomStatus::VacantClean, RoomStatus::Reserved], true);
    }

    private function countSellableRooms(int $hotelId): int
    {
        return Room::query()
            ->where('hotel_id', $hotelId)
            ->whereNotIn('status', [
                RoomStatus::OutOfOrder->value,
                RoomStatus::OutOfService->value,
            ])
            ->count();
    }

    private function countOccupiedRoomsOnDate(int $hotelId, Carbon $date): int
    {
        return ReservationRoom::query()
            ->join('reservations', 'reservation_rooms.reservation_id', '=', 'reservations.id')
            ->where('reservations.hotel_id', $hotelId)
            ->whereIn('reservation_rooms.status', [
                ReservationRoomStatus::Booked->value,
                ReservationRoomStatus::CheckedIn->value,
                ReservationRoomStatus::CheckedOut->value,
            ])
            ->whereDate('reservations.arrival_date', '<=', $date)
            ->whereDate('reservations.departure_date', '>', $date)
            ->count();
    }
}
