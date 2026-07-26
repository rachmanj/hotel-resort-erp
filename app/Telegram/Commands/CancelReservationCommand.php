<?php

namespace App\Telegram\Commands;

use App\Actions\Reservations\CancelReservationAction;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Telegram\TelegramConversationManager;
use App\Telegram\TelegramResponder;

class CancelReservationCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private TelegramConversationManager $conversationManager,
        private CancelReservationAction $cancelReservation,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('reservations.cancel') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'reservations.cancel')) {
            return;
        }

        if (empty($args)) {
            $this->reply($tgUser, 'Usage: /cancelres <reservation_code> [reason]');

            return;
        }

        $code = strtoupper($args[0]);
        $reason = count($args) > 1 ? implode(' ', array_slice($args, 1)) : null;

        if ($reason === null) {
            $this->conversationManager->startFlow($tgUser, 'cancel_reservation', [
                'reservation_code' => $code,
            ]);
            $this->conversationManager->advanceStep(
                $this->conversationManager->getActiveFlow($tgUser),
                'reason',
            );
            $this->reply($tgUser, "Please provide a cancellation reason for {$code}:");

            return;
        }

        $this->cancelByCode($tgUser, $code, $reason);
    }

    public function handleFlowStep(TelegramUser $tgUser, TelegramConversationState $state, string $input): void
    {
        if ($state->flow !== 'cancel_reservation' || $state->step !== 'reason') {
            return;
        }

        $code = $state->payload['reservation_code'] ?? '';
        $this->cancelByCode($tgUser, $code, trim($input));
        $this->conversationManager->completeFlow($state);
    }

    private function cancelByCode(TelegramUser $tgUser, string $code, string $reason): void
    {
        $this->setHotelContext($tgUser);

        $reservation = Reservation::query()
            ->where('reservation_code', $code)
            ->first();

        if ($reservation === null) {
            $this->reply($tgUser, '❌ Reservation not found.');

            return;
        }

        if ($reservation->status === ReservationStatus::Cancelled) {
            $this->reply($tgUser, '❌ Reservation is already cancelled.');

            return;
        }

        ($this->cancelReservation)($reservation, ['cancelled_reason' => $reason]);

        $this->reply($tgUser, "✅ Reservation {$code} has been cancelled.");
    }
}
