<?php

namespace App\Telegram\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use Carbon\Carbon;

class MyRoomsCommand extends BaseCommand
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

        $this->setHotelContext($tgUser);

        $today = Carbon::today()->toDateString();

        $reservations = Reservation::query()
            ->with(['guest:id,full_name', 'reservationRooms.room:id,number', 'reservationRooms.roomType:id,name'])
            ->when($tgUser->hotel_id !== null, fn ($q) => $q->where('hotel_id', $tgUser->hotel_id))
            ->whereNotIn('status', [ReservationStatus::Cancelled->value, ReservationStatus::NoShow->value])
            ->where(function ($query) use ($today): void {
                $query->where('arrival_date', $today)
                    ->orWhere('departure_date', $today)
                    ->orWhere(function ($q) use ($today): void {
                        $q->where('arrival_date', '<=', $today)
                            ->where('departure_date', '>', $today);
                    });
            })
            ->orderBy('arrival_date')
            ->get();

        if ($reservations->isEmpty()) {
            $this->reply($tgUser, '📋 No active reservations for today.');

            return;
        }

        $lines = $reservations->map(function (Reservation $reservation) use ($today) {
            $room = $reservation->reservationRooms->first();
            $roomNumber = $room?->room?->number ?? 'TBA';
            $roomType = $room?->roomType?->name ?? 'Unknown';

            $tag = match (true) {
                $reservation->arrival_date->toDateString() === $today => '📥 Arrival',
                $reservation->departure_date->toDateString() === $today => '📤 Departure',
                default => '🏠 In-house',
            };

            return sprintf(
                "%s %s — Room %s (%s)\n   %s",
                $tag,
                $reservation->reservation_code,
                $roomNumber,
                $roomType,
                $reservation->guest?->full_name ?? 'Unknown',
            );
        })->implode("\n\n");

        $this->reply($tgUser, "📋 Today's Rooms\n\n{$lines}");
    }
}
