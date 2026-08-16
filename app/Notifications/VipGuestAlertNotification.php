<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class VipGuestAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Reservation $reservation,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram(object $notifiable): string
    {
        $guest = $this->reservation->guest;
        $tier = $guest?->vip_tier?->label() ?? 'VIP';
        $roomNumbers = $this->reservation->reservationRooms
            ->map(fn ($rr) => $rr->room?->number)
            ->filter()
            ->implode(', ');

        return "⭐ <b>VIP Guest Check-In</b>\n\n".
            "Guest: {$guest?->full_name}\n".
            "Tier: {$tier}\n".
            "Reservation: {$this->reservation->reservation_code}\n".
            'Room(s): '.($roomNumbers ?: '–');
    }
}
