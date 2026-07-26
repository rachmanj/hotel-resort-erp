<?php

namespace App\Telegram\Commands;

use App\Models\Room;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;

class RoomStatusCommand extends BaseCommand
{
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
            $this->reply($tgUser, 'Usage: /roomstatus <room_number>');

            return;
        }

        $this->setHotelContext($tgUser);

        $room = Room::query()
            ->with('roomType:id,name')
            ->when($tgUser->hotel_id !== null, fn ($q) => $q->where('hotel_id', $tgUser->hotel_id))
            ->where('number', $args[0])
            ->first();

        if ($room === null) {
            $this->reply($tgUser, "❌ Room {$args[0]} not found.");

            return;
        }

        $emoji = match ($room->status->value) {
            'vacant_clean', 'vacant_dirty' => '🟢',
            'occupied_clean', 'occupied_dirty' => '🔴',
            'out_of_order', 'out_of_service' => '⚫',
            default => '🟡',
        };

        $this->reply(
            $tgUser,
            "🏨 Room {$room->number} ({$room->roomType?->name})\n".
            "Status: {$emoji} {$room->status->label()}",
        );
    }
}
