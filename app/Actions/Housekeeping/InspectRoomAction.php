<?php

namespace App\Actions\Housekeeping;

use App\Enums\HousekeepingStatus;
use App\Models\Room;
use App\Models\TelegramUser;
use App\Models\User;
use App\Notifications\RoomReadyNotification;
use App\Services\HousekeepingService;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class InspectRoomAction
{
    public function __construct(
        private HousekeepingService $housekeepingService,
    ) {}

    public function __invoke(Room $room, User $performedBy, string $via = 'web'): void
    {
        if ($this->housekeepingService->isRoomOccupied($room)) {
            throw new InvalidArgumentException('Occupied rooms cannot be inspected for vacancy readiness.');
        }

        $currentStatus = $this->housekeepingService->resolveHousekeepingStatus($room);

        if ($currentStatus !== HousekeepingStatus::Clean) {
            throw new InvalidArgumentException('Room must be clean before inspection.');
        }

        $this->housekeepingService->logStatusChange(
            $room,
            HousekeepingStatus::Inspected->value,
            $performedBy,
            $via,
        );

        $this->housekeepingService->logStatusChange(
            $room,
            HousekeepingStatus::Ready->value,
            $performedBy,
            $via,
        );

        $this->dispatchRoomReadyAlert($room);
    }

    private function dispatchRoomReadyAlert(Room $room): void
    {
        $telegramUsers = TelegramUser::query()
            ->where('is_active', true)
            ->whereNotNull('chat_id')
            ->whereHas('user', function ($query): void {
                $query->role(['front_office', 'manager', 'admin']);
            })
            ->when($room->hotel_id, fn ($q) => $q->where('hotel_id', $room->hotel_id))
            ->get();

        if ($telegramUsers->isEmpty()) {
            return;
        }

        Notification::send($telegramUsers, new RoomReadyNotification($room));
    }
}
