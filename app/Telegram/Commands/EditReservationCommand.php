<?php

namespace App\Telegram\Commands;

use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Telegram\TelegramConversationManager;
use App\Telegram\TelegramResponder;
use Carbon\Carbon;

class EditReservationCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private TelegramConversationManager $conversationManager,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('reservations.edit') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'reservations.edit')) {
            return;
        }

        if (empty($args)) {
            $this->reply($tgUser, 'Usage: /editres <reservation_code>');

            return;
        }

        $this->setHotelContext($tgUser);

        $reservation = Reservation::query()
            ->where('reservation_code', strtoupper($args[0]))
            ->with(['guest', 'reservationRooms.roomType'])
            ->first();

        if ($reservation === null) {
            $this->reply($tgUser, '❌ Reservation not found.');

            return;
        }

        $state = $this->conversationManager->startFlow($tgUser, 'edit_reservation', [
            'reservation_id' => $reservation->id,
            'reservation_code' => $reservation->reservation_code,
        ]);

        $this->showReservationMenu($tgUser, $reservation);
    }

    public function handleFlowStep(TelegramUser $tgUser, TelegramConversationState $state, string $input): void
    {
        if (! $this->requirePermission($tgUser, 'reservations.edit')) {
            return;
        }

        $this->setHotelContext($tgUser);

        $reservation = Reservation::query()->find($state->payload['reservation_id'] ?? 0);

        if ($reservation === null) {
            $this->conversationManager->cancelFlow($state);
            $this->reply($tgUser, '❌ Reservation not found.');

            return;
        }

        match ($state->step) {
            'edit_arrival' => $this->updateArrival($tgUser, $state, $reservation, $input),
            'edit_departure' => $this->updateDeparture($tgUser, $state, $reservation, $input),
            'edit_guest_name' => $this->updateGuestName($tgUser, $state, $reservation, $input),
            'edit_guest_phone' => $this->updateGuestPhone($tgUser, $state, $reservation, $input),
            default => null,
        };
    }

    public function handleCallback(TelegramUser $tgUser, TelegramConversationState $state, string $action): void
    {
        if (! $this->requirePermission($tgUser, 'reservations.edit')) {
            return;
        }

        $this->setHotelContext($tgUser);

        $reservation = Reservation::query()
            ->with(['guest', 'reservationRooms.roomType'])
            ->find($state->payload['reservation_id'] ?? 0);

        if ($reservation === null) {
            $this->conversationManager->cancelFlow($state);
            $this->reply($tgUser, '❌ Reservation not found.');

            return;
        }

        match ($action) {
            'dates' => $this->startEditDates($tgUser, $state),
            'room_type' => $this->startEditRoomType($tgUser, $state, $reservation),
            'guest' => $this->startEditGuest($tgUser, $state),
            'cancel' => $this->cancelEdit($tgUser, $state),
            'back' => $this->showReservationMenu($tgUser, $reservation),
            default => null,
        };
    }

    private function showReservationMenu(TelegramUser $tgUser, Reservation $reservation): void
    {
        $roomType = $reservation->reservationRooms->first()?->roomType;

        $summary = sprintf(
            "📋 %s\n".
            "📅 %s → %s\n".
            "🛏 %s\n".
            "👤 %s (%s)\n\n".
            'What would you like to edit?',
            $reservation->reservation_code,
            $reservation->arrival_date->format('d M Y'),
            $reservation->departure_date->format('d M Y'),
            $roomType?->name ?? 'Unknown',
            $reservation->guest?->full_name ?? 'Unknown',
            $reservation->guest?->phone ?? 'N/A',
        );

        $this->responder->sendInlineKeyboard((int) $tgUser->chat_id, $summary, [
            [
                ['text' => '📅 Edit Dates', 'callback_data' => 'editres:dates'],
                ['text' => '🛏 Change Room Type', 'callback_data' => 'editres:room_type'],
            ],
            [
                ['text' => '👤 Edit Guest', 'callback_data' => 'editres:guest'],
                ['text' => '❌ Cancel', 'callback_data' => 'editres:cancel'],
            ],
        ]);
    }

    private function startEditDates(TelegramUser $tgUser, TelegramConversationState $state): void
    {
        $this->conversationManager->advanceStep($state, 'edit_arrival');
        $this->reply($tgUser, 'New check-in date? (YYYY-MM-DD)');
    }

    private function updateArrival(TelegramUser $tgUser, TelegramConversationState $state, Reservation $reservation, string $input): void
    {
        try {
            $date = Carbon::parse($input)->startOfDay();
        } catch (\Exception) {
            $this->reply($tgUser, '❌ Invalid date. Use YYYY-MM-DD.');

            return;
        }

        $this->conversationManager->advanceStep($state, 'edit_departure', ['arrival_date' => $date->toDateString()]);
        $this->reply($tgUser, 'New check-out date? (YYYY-MM-DD)');
    }

    private function updateDeparture(TelegramUser $tgUser, TelegramConversationState $state, Reservation $reservation, string $input): void
    {
        try {
            $checkout = Carbon::parse($input)->startOfDay();
            $checkin = Carbon::parse($state->payload['arrival_date'])->startOfDay();
        } catch (\Exception) {
            $this->reply($tgUser, '❌ Invalid date. Use YYYY-MM-DD.');

            return;
        }

        if ($checkout->lte($checkin)) {
            $this->reply($tgUser, '❌ Check-out must be after check-in.');

            return;
        }

        $reservation->update([
            'arrival_date' => $state->payload['arrival_date'],
            'departure_date' => $checkout->toDateString(),
        ]);

        $this->conversationManager->advanceStep($state, 'menu');
        $this->reply($tgUser, "✅ Dates updated for {$reservation->reservation_code}.");
        $this->showReservationMenu($tgUser, $reservation->fresh(['guest', 'reservationRooms.roomType']));
    }

    private function startEditRoomType(TelegramUser $tgUser, TelegramConversationState $state, Reservation $reservation): void
    {
        $roomTypes = RoomType::query()->where('is_active', true)->orderBy('name')->get();

        $buttons = $roomTypes->map(fn (RoomType $rt) => [[
            'text' => $rt->name,
            'callback_data' => "editres:rt:{$rt->id}",
        ]])->values()->all();

        $buttons[] = [['text' => '⬅️ Back', 'callback_data' => 'editres:back']];

        $this->responder->sendInlineKeyboard((int) $tgUser->chat_id, 'Select new room type:', $buttons);
    }

    public function handleRoomTypeCallback(TelegramUser $tgUser, TelegramConversationState $state, int $roomTypeId): void
    {
        $reservation = Reservation::query()->find($state->payload['reservation_id'] ?? 0);

        if ($reservation === null) {
            return;
        }

        $reservationRoom = $reservation->reservationRooms()->first();

        if ($reservationRoom !== null) {
            $roomType = RoomType::query()->findOrFail($roomTypeId);
            $reservationRoom->update([
                'room_type_id' => $roomTypeId,
                'nightly_rate' => $roomType->base_rate,
            ]);
        }

        $this->reply($tgUser, "✅ Room type updated for {$reservation->reservation_code}.");
        $this->showReservationMenu($tgUser, $reservation->fresh(['guest', 'reservationRooms.roomType']));
    }

    private function startEditGuest(TelegramUser $tgUser, TelegramConversationState $state): void
    {
        $this->conversationManager->advanceStep($state, 'edit_guest_name');
        $this->reply($tgUser, 'New guest full name?');
    }

    private function updateGuestName(TelegramUser $tgUser, TelegramConversationState $state, Reservation $reservation, string $input): void
    {
        if (strlen(trim($input)) < 2) {
            $this->reply($tgUser, '❌ Please enter a valid name.');

            return;
        }

        $this->conversationManager->advanceStep($state, 'edit_guest_phone', ['guest_name' => trim($input)]);
        $this->reply($tgUser, 'New guest phone number?');
    }

    private function updateGuestPhone(TelegramUser $tgUser, TelegramConversationState $state, Reservation $reservation, string $input): void
    {
        $phone = trim($input);

        if (strlen($phone) < 6) {
            $this->reply($tgUser, '❌ Please enter a valid phone number.');

            return;
        }

        $reservation->guest?->update([
            'full_name' => $state->payload['guest_name'],
            'phone' => $phone,
        ]);

        $this->conversationManager->advanceStep($state, 'menu');
        $this->reply($tgUser, "✅ Guest info updated for {$reservation->reservation_code}.");
        $this->showReservationMenu($tgUser, $reservation->fresh(['guest', 'reservationRooms.roomType']));
    }

    private function cancelEdit(TelegramUser $tgUser, TelegramConversationState $state): void
    {
        $this->conversationManager->cancelFlow($state);
        $this->reply($tgUser, 'Edit cancelled.');
    }
}
