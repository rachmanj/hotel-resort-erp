<?php

namespace App\Telegram\Commands;

use App\Actions\Reservations\CheckInGuestAction;
use App\Enums\ReservationStatus;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use InvalidArgumentException;

class CheckInCommand extends BaseCommand
{
    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('reservations.checkin') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'reservations.checkin')) {
            return;
        }

        if (empty($args)) {
            $this->reply($tgUser, 'Usage: /checkin <reservation_code>');

            return;
        }

        $this->setHotelContext($tgUser);

        $code = strtoupper($args[0]);

        $reservation = Reservation::query()
            ->with(['guest', 'reservationRooms.room'])
            ->when($tgUser->hotel_id !== null, fn ($q) => $q->where('hotel_id', $tgUser->hotel_id))
            ->where('reservation_code', $code)
            ->first();

        if ($reservation === null) {
            $this->reply($tgUser, "❌ Reservation {$code} not found.");

            return;
        }

        if ($reservation->status !== ReservationStatus::Confirmed) {
            $this->reply($tgUser, "❌ Reservation {$code} is not in confirmed status.");

            return;
        }

        if (! $reservation->arrival_date->isToday()) {
            $this->reply($tgUser, "❌ Arrival date for {$code} is not today ({$reservation->arrival_date->format('d M Y')}).");

            return;
        }

        try {
            app(CheckInGuestAction::class)($reservation, $tgUser->user);
        } catch (InvalidArgumentException $e) {
            $this->reply($tgUser, "❌ {$e->getMessage()}");

            return;
        }

        $folio = Folio::query()
            ->where('reservation_id', $reservation->id)
            ->where('type', 'master')
            ->first();

        $roomNumbers = $reservation->reservationRooms
            ->map(fn ($rr) => $rr->room?->number)
            ->filter()
            ->implode(', ');

        $this->reply(
            $tgUser,
            "✅ Guest checked in.\n".
            "Room {$roomNumbers}.\n".
            'Folio '.($folio?->folio_no ?? '—').' opened.',
        );
    }
}
