<?php

namespace App\Telegram\Commands;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;

class RoomsCommand extends BaseCommand
{
    private const PER_PAGE = 10;

    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('rooms.view') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'rooms.view')) {
            return;
        }

        $this->setHotelContext($tgUser);

        $statusFilter = isset($args[0]) ? strtolower($args[0]) : 'all';
        $this->sendPage($tgUser, $statusFilter, 1);
    }

    public function handleCallback(TelegramUser $tgUser, string $statusFilter, int $page): void
    {
        if (! $this->requirePermission($tgUser, 'rooms.view')) {
            return;
        }

        $this->setHotelContext($tgUser);
        $this->sendPage($tgUser, $statusFilter, $page);
    }

    private function sendPage(TelegramUser $tgUser, string $statusFilter, int $page): void
    {
        $query = Room::query()
            ->with('roomType:id,name')
            ->when($tgUser->hotel_id !== null, fn ($q) => $q->where('hotel_id', $tgUser->hotel_id))
            ->orderBy('number');

        if ($statusFilter !== 'all') {
            $statusMap = [
                'vacant' => [RoomStatus::VacantClean, RoomStatus::VacantDirty],
                'occupied' => [RoomStatus::OccupiedClean, RoomStatus::OccupiedDirty],
                'dirty' => [RoomStatus::VacantDirty, RoomStatus::OccupiedDirty],
                'out_of_order' => [RoomStatus::OutOfOrder, RoomStatus::OutOfService],
            ];

            if (! isset($statusMap[$statusFilter])) {
                $this->reply($tgUser, 'Invalid status filter. Use: vacant, occupied, dirty, out_of_order, or all');

                return;
            }

            $query->whereIn('status', array_map(fn (RoomStatus $s) => $s->value, $statusMap[$statusFilter]));
        }

        $total = $query->count();
        $rooms = $query->forPage($page, self::PER_PAGE)->get();

        if ($rooms->isEmpty()) {
            $this->reply($tgUser, 'No rooms found.');

            return;
        }

        $lines = $rooms->map(fn (Room $room) => sprintf(
            '%s %s — %s (%s)',
            $this->statusEmoji($room->status),
            $room->number,
            $room->roomType?->name ?? 'Unknown',
            $room->status->label(),
        ))->implode("\n");

        $totalPages = (int) ceil($total / self::PER_PAGE);
        $header = "🏨 Rooms (page {$page}/{$totalPages})\n\n";

        $buttons = [];
        $navRow = [];

        if ($page > 1) {
            $navRow[] = [
                'text' => '⬅️ Previous',
                'callback_data' => "rooms:{$statusFilter}:".($page - 1),
            ];
        }

        if ($page < $totalPages) {
            $navRow[] = [
                'text' => 'Next ➡️',
                'callback_data' => "rooms:{$statusFilter}:".($page + 1),
            ];
        }

        if (! empty($navRow)) {
            $buttons[] = $navRow;
        }

        if (! empty($buttons)) {
            $this->responder->sendInlineKeyboard((int) $tgUser->chat_id, $header.$lines, $buttons);
        } else {
            $this->reply($tgUser, $header.$lines);
        }
    }

    private function statusEmoji(RoomStatus $status): string
    {
        return match ($status) {
            RoomStatus::VacantClean => '🟢',
            RoomStatus::VacantDirty => '🟡',
            RoomStatus::OccupiedClean, RoomStatus::OccupiedDirty => '🔴',
            RoomStatus::OutOfOrder, RoomStatus::OutOfService => '⚫',
            RoomStatus::Reserved => '🟣',
        };
    }
}
