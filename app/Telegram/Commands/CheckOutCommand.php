<?php

namespace App\Telegram\Commands;

use App\Actions\Reservations\CheckOutGuestAction;
use App\Enums\ReservationRoomStatus;
use App\Models\ReservationRoom;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use InvalidArgumentException;

class CheckOutCommand extends BaseCommand
{
    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('reservations.checkout') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'reservations.checkout')) {
            return;
        }

        if (empty($args)) {
            $this->reply($tgUser, 'Usage: /checkout <room_number>');

            return;
        }

        $this->setHotelContext($tgUser);

        $roomNumber = $args[0];

        $reservationRoom = ReservationRoom::query()
            ->with(['reservation.guest', 'room'])
            ->where('status', ReservationRoomStatus::CheckedIn->value)
            ->whereHas('room', function ($q) use ($roomNumber, $tgUser): void {
                $q->where('number', $roomNumber);
                if ($tgUser->hotel_id !== null) {
                    $q->where('hotel_id', $tgUser->hotel_id);
                }
            })
            ->first();

        if ($reservationRoom === null) {
            $this->reply($tgUser, "❌ No checked-in guest found in room {$roomNumber}.");

            return;
        }

        try {
            $result = app(CheckOutGuestAction::class)($reservationRoom, $tgUser->user);
        } catch (InvalidArgumentException $e) {
            $this->reply($tgUser, "❌ {$e->getMessage()}");

            return;
        }

        $this->reply(
            $tgUser,
            "✅ Checkout complete for Room {$roomNumber}.\n".
            'Folio balance: '.$this->formatIdr($result['balance']).'.',
        );
    }
}
