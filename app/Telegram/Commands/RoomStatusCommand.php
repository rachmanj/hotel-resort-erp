<?php

namespace App\Telegram\Commands;

use App\Actions\Housekeeping\MarkCleanAction;
use App\Actions\Housekeeping\StartCleaningAction;
use App\Enums\HousekeepingStatus;
use App\Models\Room;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\HousekeepingService;
use App\Telegram\TelegramResponder;
use InvalidArgumentException;

class RoomStatusCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private HousekeepingService $housekeepingService,
        private StartCleaningAction $startCleaningAction,
        private MarkCleanAction $markCleanAction,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('rooms.view') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'rooms.view')) {
            return;
        }

        if (empty($args)) {
            $this->reply($tgUser, 'Usage: /roomstatus <room_number> [status]');

            return;
        }

        $this->setHotelContext($tgUser);

        $room = Room::query()
            ->with(['roomType:id,name', 'latestHousekeepingLog', 'housekeepingAssignments' => fn ($q) => $q
                ->whereDate('assignment_date', today())
                ->with('housekeeper:id,name')])
            ->when($tgUser->hotel_id !== null, fn ($q) => $q->where('hotel_id', $tgUser->hotel_id))
            ->where('number', $args[0])
            ->first();

        if ($room === null) {
            $this->reply($tgUser, "❌ Room {$args[0]} not found.");

            return;
        }

        if (isset($args[1])) {
            $this->updateStatus($tgUser, $room, strtolower($args[1]));

            return;
        }

        $this->showRoomStatus($tgUser, $room);
    }

    private function showRoomStatus(TelegramUser $tgUser, Room $room): void
    {
        $hkStatus = $this->housekeepingService->resolveHousekeepingStatus($room);
        $lastLog = $room->latestHousekeepingLog;
        $assignment = $room->housekeepingAssignments->first();

        $lines = [
            "🏨 Room {$room->number} ({$room->roomType?->name})",
            "Status: {$hkStatus->emoji()} {$hkStatus->label()}",
        ];

        if ($assignment?->housekeeper !== null) {
            $lines[] = "Housekeeper: {$assignment->housekeeper->name}";
        }

        if ($lastLog !== null && $hkStatus === HousekeepingStatus::Cleaning) {
            $lines[] = 'Started: '.$lastLog->changed_at->format('H:i').' ('.$lastLog->changed_at->diffForHumans(short: true).')';
        }

        $lastCleaned = $this->getLastCleanedAt($room);
        if ($lastCleaned !== null) {
            $lines[] = "Last cleaned: {$lastCleaned}";
        }

        $this->reply($tgUser, implode("\n", $lines));
    }

    private function updateStatus(TelegramUser $tgUser, Room $room, string $status): void
    {
        if (! $this->requirePermission($tgUser, 'housekeeping.update_status')) {
            return;
        }

        $user = $tgUser->user;

        if ($user === null) {
            return;
        }

        try {
            match ($status) {
                'cleaning' => $this->startCleaningAction->__invoke($room, $user, 'telegram'),
                'clean' => $this->markCleanAction->__invoke($room, $user, 'telegram'),
                'dirty' => $this->housekeepingService->logStatusChange($room, HousekeepingStatus::Dirty->value, $user, 'telegram'),
                default => throw new InvalidArgumentException('Invalid status. Use: cleaning, clean, dirty'),
            };
        } catch (InvalidArgumentException $e) {
            $this->reply($tgUser, "❌ {$e->getMessage()}");

            return;
        }

        $message = match ($status) {
            'cleaning' => "🟡 Room {$room->number} marked as Cleaning.",
            'clean' => "✅ Room {$room->number} marked as Clean. Awaiting inspection.",
            'dirty' => "🔴 Room {$room->number} marked as Dirty.",
            default => "Room {$room->number} status updated.",
        };

        $this->reply($tgUser, $message);
    }

    private function getLastCleanedAt(Room $room): ?string
    {
        $log = $room->housekeepingLogs()
            ->whereIn('status', [HousekeepingStatus::Clean->value, HousekeepingStatus::Ready->value])
            ->latest('changed_at')
            ->first();

        return $log?->changed_at?->format('Y-m-d H:i');
    }
}
