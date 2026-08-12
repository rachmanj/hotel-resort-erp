<?php

namespace App\Telegram\Commands;

use App\Actions\Reservations\CreateReservationAction;
use App\Enums\CreatedVia;
use App\Enums\ReservationSource;
use App\Exceptions\RoomNotAvailableException;
use App\Models\RoomType;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\AvailabilityService;
use App\Telegram\TelegramConversationManager;
use App\Telegram\TelegramResponder;
use Carbon\Carbon;

class NewReservationCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private TelegramConversationManager $conversationManager,
        private AvailabilityService $availabilityService,
        private CreateReservationAction $createReservation,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('reservations.create') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'reservations.create')) {
            return;
        }

        if ($tgUser->hotel_id === null) {
            $this->reply($tgUser, '❌ No property selected. Use /switchproperty first.');

            return;
        }

        $state = $this->conversationManager->startFlow($tgUser, 'new_reservation');
        $this->conversationManager->advanceStep($state, 'checkin_date');

        $this->reply($tgUser, 'What is the check-in date? (YYYY-MM-DD)');
    }

    public function handleFlowStep(TelegramUser $tgUser, TelegramConversationState $state, string $input): void
    {
        if (! $this->requirePermission($tgUser, 'reservations.create')) {
            return;
        }

        $this->setHotelContext($tgUser);

        match ($state->step) {
            'checkin_date' => $this->handleCheckinDate($tgUser, $state, $input),
            'checkout_date' => $this->handleCheckoutDate($tgUser, $state, $input),
            'guests' => $this->handleGuests($tgUser, $state, $input),
            'children' => $this->handleChildren($tgUser, $state, $input),
            'guest_name' => $this->handleGuestName($tgUser, $state, $input),
            'guest_phone' => $this->handleGuestPhone($tgUser, $state, $input),
            default => null,
        };
    }

    public function handleCallback(TelegramUser $tgUser, TelegramConversationState $state, string $action, ?string $value = null): void
    {
        if (! $this->requirePermission($tgUser, 'reservations.create')) {
            return;
        }

        $this->setHotelContext($tgUser);

        if ($action === 'room_type' && $value !== null) {
            $this->conversationManager->advanceStep($state, 'guests', ['room_type_id' => (int) $value]);
            $this->reply($tgUser, 'Number of adults?');

            return;
        }

        if ($action === 'confirm') {
            $this->confirmReservation($tgUser, $state);

            return;
        }

        if ($action === 'cancel') {
            $this->conversationManager->cancelFlow($state);
            $this->reply($tgUser, '❌ Reservation cancelled.');
        }
    }

    private function handleCheckinDate(TelegramUser $tgUser, TelegramConversationState $state, string $input): void
    {
        try {
            $date = Carbon::parse($input)->startOfDay();
        } catch (\Exception) {
            $this->reply($tgUser, '❌ Invalid date. Please enter check-in date as YYYY-MM-DD.');

            return;
        }

        if ($date->lt(now()->startOfDay())) {
            $this->reply($tgUser, '❌ Check-in date cannot be in the past.');

            return;
        }

        $this->conversationManager->advanceStep($state, 'checkout_date', [
            'arrival_date' => $date->toDateString(),
        ]);

        $this->reply($tgUser, 'Check-out date? (YYYY-MM-DD)');
    }

    private function handleCheckoutDate(TelegramUser $tgUser, TelegramConversationState $state, string $input): void
    {
        try {
            $checkout = Carbon::parse($input)->startOfDay();
            $checkin = Carbon::parse($state->payload['arrival_date'])->startOfDay();
        } catch (\Exception) {
            $this->reply($tgUser, '❌ Invalid date. Please enter check-out date as YYYY-MM-DD.');

            return;
        }

        if ($checkout->lte($checkin)) {
            $this->reply($tgUser, '❌ Check-out must be after check-in.');

            return;
        }

        $this->conversationManager->advanceStep($state, 'room_type', [
            'departure_date' => $checkout->toDateString(),
        ]);

        $availability = $this->availabilityService->getAvailability($checkin, $checkout, $tgUser->hotel_id);
        $available = array_filter($availability, fn (array $row) => $row['available_count'] > 0);

        if (empty($available)) {
            $this->conversationManager->cancelFlow($state);
            $this->reply($tgUser, '❌ No rooms available for those dates. Please start over with /newres.');

            return;
        }

        $buttons = collect($available)->map(fn (array $row) => [[
            'text' => "{$row['name']} ({$row['available_count']} avail.)",
            'callback_data' => "newres:room_type:{$row['room_type_id']}",
        ]])->values()->all();

        $this->responder->sendInlineKeyboard((int) $tgUser->chat_id, 'Select room type:', $buttons);
    }

    private function handleGuests(TelegramUser $tgUser, TelegramConversationState $state, string $input): void
    {
        if (! ctype_digit($input) || (int) $input < 1) {
            $this->reply($tgUser, '❌ Please enter a valid number of adults (1 or more).');

            return;
        }

        $this->conversationManager->advanceStep($state, 'children', ['adults' => (int) $input]);
        $this->reply($tgUser, 'Number of children?');
    }

    private function handleChildren(TelegramUser $tgUser, TelegramConversationState $state, string $input): void
    {
        if (! ctype_digit($input) || (int) $input < 0) {
            $this->reply($tgUser, '❌ Please enter a valid number of children (0 or more).');

            return;
        }

        $this->conversationManager->advanceStep($state, 'guest_name', ['children' => (int) $input]);
        $this->reply($tgUser, 'Guest full name?');
    }

    private function handleGuestName(TelegramUser $tgUser, TelegramConversationState $state, string $input): void
    {
        if (strlen(trim($input)) < 2) {
            $this->reply($tgUser, '❌ Please enter the guest full name.');

            return;
        }

        $this->conversationManager->advanceStep($state, 'guest_phone', ['guest_name' => trim($input)]);
        $this->reply($tgUser, 'Guest phone number?');
    }

    private function handleGuestPhone(TelegramUser $tgUser, TelegramConversationState $state, string $input): void
    {
        $phone = trim($input);

        if (strlen($phone) < 6) {
            $this->reply($tgUser, '❌ Please enter a valid phone number.');

            return;
        }

        $state = $this->conversationManager->advanceStep($state, 'confirm', ['guest_phone' => $phone]);
        $this->showConfirmation($tgUser, $state);
    }

    private function showConfirmation(TelegramUser $tgUser, TelegramConversationState $state): void
    {
        $payload = $state->payload;
        $checkin = Carbon::parse($payload['arrival_date']);
        $checkout = Carbon::parse($payload['departure_date']);
        $nights = $checkin->diffInDays($checkout);
        $roomType = RoomType::query()->find($payload['room_type_id']);
        $estimatedTotal = $roomType ? (float) $roomType->base_rate * $nights : 0;

        $summary = sprintf(
            "📅 %s–%s (%d night%s)\n".
            "🛏 %s\n".
            "👤 %s, %s\n".
            '💰 Est. total: %s + tax',
            $checkin->format('d M'),
            $checkout->format('d M'),
            $nights,
            $nights === 1 ? '' : 's',
            $roomType?->name ?? 'Unknown',
            $payload['guest_name'],
            $payload['guest_phone'],
            $this->formatIdr($estimatedTotal),
        );

        $this->responder->sendInlineKeyboard((int) $tgUser->chat_id, $summary, [
            [
                ['text' => '✅ Confirm', 'callback_data' => 'newres:confirm'],
                ['text' => '❌ Cancel', 'callback_data' => 'newres:cancel'],
            ],
        ]);
    }

    private function confirmReservation(TelegramUser $tgUser, TelegramConversationState $state): void
    {
        $payload = $state->payload;

        try {
            $reservation = ($this->createReservation)([
                'hotel_id' => $tgUser->hotel_id,
                'arrival_date' => $payload['arrival_date'],
                'departure_date' => $payload['departure_date'],
                'room_type_id' => $payload['room_type_id'],
                'adults' => $payload['adults'],
                'children' => $payload['children'],
                'guest' => [
                    'full_name' => $payload['guest_name'],
                    'phone' => $payload['guest_phone'],
                ],
                'source' => ReservationSource::Walkin->value,
                'created_by' => $tgUser->user_id,
                'created_via' => CreatedVia::Telegram->value,
            ], $tgUser->user);
        } catch (RoomNotAvailableException $e) {
            $this->conversationManager->cancelFlow($state);
            $this->reply($tgUser, '❌ '.$e->getMessage());

            return;
        } catch (\Throwable $e) {
            $this->conversationManager->cancelFlow($state);
            $this->reply($tgUser, '❌ Failed to create reservation. Please try again.');

            return;
        }

        $this->conversationManager->completeFlow($state);
        $this->reply($tgUser, "✅ Reservation {$reservation->reservation_code} created.");
    }
}
