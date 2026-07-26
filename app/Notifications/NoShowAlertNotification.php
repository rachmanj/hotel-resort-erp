<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NoShowAlertNotification extends Notification implements ShouldQueue
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

        return "⚠️ <b>No-Show Alert</b>\n\n".
            "Reservation: {$this->reservation->reservation_code}\n".
            'Guest: '.($guest?->full_name ?? 'Unknown')."\n".
            "Arrival: {$this->reservation->arrival_date->format('d M Y')}\n".
            'Status: Guest has not checked in.';
    }
}
