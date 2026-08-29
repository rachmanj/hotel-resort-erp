<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReservationCancelledNotification extends Notification implements ShouldQueue
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
        return ['whatsapp'];
    }

    public function toWhatsApp(object $notifiable): string
    {
        $reservation = $this->reservation->loadMissing(['guest']);

        $lines = [
            "Hello {$reservation->guest?->full_name} 👋",
            '',
            "Your reservation **{$reservation->reservation_code}** at Pratasaba Resort has been **cancelled**.",
        ];

        if ($reservation->cancelled_reason) {
            $lines[] = '';
            $lines[] = "Reason: {$reservation->cancelled_reason}";
        }

        $lines = array_merge($lines, [
            '',
            'If you would like to make a new booking or have any questions, simply reply to this message.',
            '',
            'We hope to welcome you some other time! 🙏',
            'Pratasaba Resort',
        ]);

        return implode("\n", $lines);
    }
}
