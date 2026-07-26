<?php

namespace App\Services;

use App\Enums\HousekeepingAssignmentStatus;
use App\Enums\HousekeepingShift;
use App\Enums\HousekeepingStatus;
use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\VipTier;
use App\Models\HousekeepingAssignment;
use App\Models\HousekeepingLog;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HousekeepingService
{
    /**
     * @return Collection<int, HousekeepingAssignment>
     */
    public function generateDailyAssignments(?int $hotelId = null, ?Carbon $date = null): Collection
    {
        $date ??= Carbon::today();
        $hotelId ??= session('current_hotel_id');

        $roomsNeedingCleaning = $this->getRoomsNeedingCleaning($hotelId, $date);

        if ($roomsNeedingCleaning->isEmpty()) {
            return collect();
        }

        $housekeepers = $this->getActiveHousekeepers($hotelId);

        if ($housekeepers->isEmpty()) {
            return collect();
        }

        $assignedBy = User::query()
            ->role('admin')
            ->when($hotelId !== null, fn ($q) => $q->where(function ($query) use ($hotelId): void {
                $query->where('hotel_id', $hotelId)
                    ->orWhereNull('hotel_id')
                    ->orWhereHas('hotels', fn ($h) => $h->where('hotels.id', $hotelId));
            }))
            ->first();

        if ($assignedBy === null) {
            $assignedBy = $housekeepers->first();
        }

        $sortedRooms = $roomsNeedingCleaning->sortByDesc(fn (Room $room) => $this->getRoomVipPriority($room))->values();

        $assignments = collect();
        $housekeeperCount = $housekeepers->count();

        DB::transaction(function () use ($sortedRooms, $housekeepers, $housekeeperCount, $date, $assignedBy, &$assignments): void {
            foreach ($sortedRooms as $index => $room) {
                $existing = HousekeepingAssignment::query()
                    ->where('room_id', $room->id)
                    ->whereDate('assignment_date', $date)
                    ->where('shift', HousekeepingShift::Morning->value)
                    ->first();

                if ($existing !== null) {
                    $assignments->push($existing);

                    continue;
                }

                $housekeeper = $housekeepers[$index % $housekeeperCount];

                $assignment = HousekeepingAssignment::query()->create([
                    'room_id' => $room->id,
                    'housekeeper_id' => $housekeeper->id,
                    'assignment_date' => $date,
                    'shift' => HousekeepingShift::Morning->value,
                    'status' => HousekeepingAssignmentStatus::Pending->value,
                    'assigned_by' => $assignedBy->id,
                ]);

                $assignments->push($assignment);
            }
        });

        return HousekeepingAssignment::query()
            ->with(['room.roomType', 'housekeeper'])
            ->whereIn('id', $assignments->pluck('id'))
            ->get();
    }

    /**
     * @param  list<int>  $roomIds
     */
    public function assignRooms(User $housekeeper, array $roomIds, Carbon $date, string $shift, User $assignedBy): void
    {
        if (! $housekeeper->hasRole('housekeeping')) {
            throw new InvalidArgumentException('User must have housekeeping role.');
        }

        DB::transaction(function () use ($housekeeper, $roomIds, $date, $shift, $assignedBy): void {
            foreach ($roomIds as $roomId) {
                HousekeepingAssignment::query()->updateOrCreate(
                    [
                        'room_id' => $roomId,
                        'assignment_date' => $date->toDateString(),
                        'shift' => $shift,
                    ],
                    [
                        'housekeeper_id' => $housekeeper->id,
                        'status' => HousekeepingAssignmentStatus::Pending->value,
                        'assigned_by' => $assignedBy->id,
                    ],
                );
            }
        });
    }

    public function logStatusChange(
        Room $room,
        string $status,
        User $changedBy,
        string $via = 'web',
        ?string $notes = null,
        ?HousekeepingAssignment $assignment = null,
    ): HousekeepingLog {
        $hkStatus = HousekeepingStatus::tryFrom($status);

        if ($hkStatus === null) {
            throw new InvalidArgumentException("Invalid housekeeping status: {$status}");
        }

        if ($assignment === null) {
            $assignment = $this->getTodayAssignmentForRoom($room);
        }

        return HousekeepingLog::query()->create([
            'room_id' => $room->id,
            'housekeeping_assignment_id' => $assignment?->id,
            'status' => $hkStatus->value,
            'changed_by' => $changedBy->id,
            'changed_via' => $via,
            'notes' => $notes,
            'changed_at' => now(),
        ]);
    }

    /**
     * @return Collection<int, HousekeepingAssignment>
     */
    public function getAssignmentsFor(User $housekeeper, ?Carbon $date = null): Collection
    {
        $date ??= Carbon::today();

        return HousekeepingAssignment::query()
            ->with(['room.roomType', 'room.latestHousekeepingLog'])
            ->where('housekeeper_id', $housekeeper->id)
            ->whereDate('assignment_date', $date)
            ->orderBy('shift')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getRoomStatusBoard(?int $hotelId = null): Collection
    {
        $hotelId ??= session('current_hotel_id');
        $today = Carbon::today()->toDateString();

        return Room::query()
            ->with([
                'roomType:id,name',
                'latestHousekeepingLog.changedBy:id,name',
                'housekeepingAssignments' => fn ($q) => $q
                    ->whereDate('assignment_date', $today)
                    ->with('housekeeper:id,name'),
            ])
            ->when($hotelId !== null, fn ($q) => $q->where('hotel_id', $hotelId))
            ->orderBy('number')
            ->get()
            ->map(function (Room $room): array {
                $hkStatus = $this->resolveHousekeepingStatus($room);
                $lastLog = $room->latestHousekeepingLog;
                $assignment = $room->housekeepingAssignments->first();

                return [
                    'id' => $room->id,
                    'number' => $room->number,
                    'room_type' => $room->roomType?->only(['id', 'name']),
                    'room_status' => $room->status->value,
                    'housekeeping_status' => $hkStatus->value,
                    'housekeeping_status_label' => $hkStatus->label(),
                    'housekeeping_status_color' => $hkStatus->color(),
                    'housekeeper' => $assignment?->housekeeper?->only(['id', 'name']),
                    'assignment_status' => $assignment?->status?->value,
                    'last_updated_at' => $lastLog?->changed_at?->toIso8601String(),
                    'last_updated_human' => $lastLog?->changed_at?->diffForHumans(),
                    'last_cleaned_at' => $this->getLastCleanedAt($room)?->format('Y-m-d H:i'),
                ];
            });
    }

    public function syncRoomStatus(Room $room): void
    {
        $latestLog = HousekeepingLog::query()
            ->where('room_id', $room->id)
            ->latest('changed_at')
            ->first();

        if ($latestLog === null) {
            return;
        }

        $occupied = $this->isRoomOccupied($room);
        $newStatus = $this->mapLogStatusToRoomStatus($latestLog->status, $occupied);

        if ($room->status !== $newStatus) {
            $room->update(['status' => $newStatus->value]);
        }
    }

    public function resolveHousekeepingStatus(Room $room): HousekeepingStatus
    {
        if ($room->relationLoaded('latestHousekeepingLog') && $room->latestHousekeepingLog !== null) {
            return $room->latestHousekeepingLog->status;
        }

        $latestLog = $room->latestHousekeepingLog;

        if ($latestLog !== null) {
            return $latestLog->status;
        }

        return HousekeepingStatus::fromRoomStatus($room->status);
    }

    public function isRoomOccupied(Room $room): bool
    {
        return ReservationRoom::query()
            ->where('room_id', $room->id)
            ->where('status', ReservationRoomStatus::CheckedIn->value)
            ->exists();
    }

    public function getTodayAssignmentForRoom(Room $room, ?Carbon $date = null): ?HousekeepingAssignment
    {
        $date ??= Carbon::today();

        return HousekeepingAssignment::query()
            ->where('room_id', $room->id)
            ->whereDate('assignment_date', $date)
            ->latest('id')
            ->first();
    }

    public function updateAssignmentStatus(Room $room, HousekeepingAssignmentStatus $status): void
    {
        $assignment = $this->getTodayAssignmentForRoom($room);

        if ($assignment !== null) {
            $assignment->update(['status' => $status->value]);
        }
    }

    /**
     * @return Collection<int, Room>
     */
    private function getRoomsNeedingCleaning(?int $hotelId, Carbon $date): Collection
    {
        $checkoutRoomIds = ReservationRoom::query()
            ->whereHas('reservation', function ($query) use ($hotelId, $date): void {
                $query->where('status', ReservationStatus::CheckedOut->value)
                    ->whereDate('departure_date', '>=', $date->copy()->subDay())
                    ->whereDate('departure_date', '<=', $date)
                    ->when($hotelId !== null, fn ($q) => $q->where('hotel_id', $hotelId));
            })
            ->whereNotNull('room_id')
            ->pluck('room_id');

        return Room::query()
            ->when($hotelId !== null, fn ($q) => $q->where('hotel_id', $hotelId))
            ->where(function ($query) use ($checkoutRoomIds): void {
                $query->whereIn('status', [
                    RoomStatus::VacantDirty->value,
                    RoomStatus::OccupiedDirty->value,
                ])
                    ->orWhereIn('id', $checkoutRoomIds);
            })
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function getActiveHousekeepers(?int $hotelId): Collection
    {
        return User::query()
            ->role('housekeeping')
            ->when($hotelId !== null, function ($query) use ($hotelId): void {
                $query->where(function ($q) use ($hotelId): void {
                    $q->where('hotel_id', $hotelId)
                        ->orWhereHas('hotels', fn ($h) => $h->where('hotels.id', $hotelId));
                });
            })
            ->orderBy('name')
            ->get();
    }

    private function getRoomVipPriority(Room $room): int
    {
        $reservationRoom = ReservationRoom::query()
            ->with('reservation.guest')
            ->where('room_id', $room->id)
            ->where('status', ReservationRoomStatus::CheckedIn->value)
            ->first();

        $tier = $reservationRoom?->reservation?->guest?->vip_tier ?? VipTier::None;

        return match ($tier) {
            VipTier::Platinum => 4,
            VipTier::Gold => 3,
            VipTier::Silver => 2,
            default => 0,
        };
    }

    private function getLastCleanedAt(Room $room): ?Carbon
    {
        $log = HousekeepingLog::query()
            ->where('room_id', $room->id)
            ->whereIn('status', [
                HousekeepingStatus::Clean->value,
                HousekeepingStatus::Ready->value,
            ])
            ->latest('changed_at')
            ->first();

        return $log?->changed_at;
    }

    private function mapLogStatusToRoomStatus(HousekeepingStatus $hkStatus, bool $occupied): RoomStatus
    {
        return match ($hkStatus) {
            HousekeepingStatus::Dirty => $occupied ? RoomStatus::OccupiedDirty : RoomStatus::VacantDirty,
            HousekeepingStatus::Cleaning => $occupied ? RoomStatus::OccupiedDirty : RoomStatus::VacantDirty,
            HousekeepingStatus::Clean => $occupied ? RoomStatus::OccupiedClean : RoomStatus::VacantDirty,
            HousekeepingStatus::Inspected => RoomStatus::VacantDirty,
            HousekeepingStatus::Ready => RoomStatus::VacantClean,
            HousekeepingStatus::OutOfOrder => RoomStatus::OutOfOrder,
        };
    }
}
