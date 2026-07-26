<?php

namespace App\Telegram\Commands;

use App\Enums\MaintenanceReportedVia;
use App\Models\Room;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\MaintenanceService;
use App\Telegram\TelegramResponder;

class MaintCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private MaintenanceService $maintenanceService,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return $tgUser->isLinked() && ($tgUser->user?->can('maintenance.create') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        $this->setHotelContext($tgUser);

        if (count($args) < 2) {
            $this->reply($tgUser, 'Usage: /maint {room_number} {description}');

            return;
        }

        $roomNumber = array_shift($args);
        $description = implode(' ', $args);

        $room = Room::query()->where('number', $roomNumber)->first();

        if ($room === null) {
            $this->reply($tgUser, "❌ Room {$roomNumber} not found.");

            return;
        }

        $request = $this->maintenanceService->createRequestForRoom(
            $room,
            $description,
            $tgUser->user,
            MaintenanceReportedVia::Telegram,
        );

        $this->reply(
            $tgUser,
            "🔧 Maintenance ticket #{$request->id} created for Room {$room->number}.\n".
            "Priority: {$request->priority->label()}\n".
            "Status: {$request->status->label()}",
        );
    }
}
