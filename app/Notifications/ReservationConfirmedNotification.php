<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReservationConfirmedNotification extends Notification implements ShouldQueue
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
        $reservation = $this->reservation->loadMissing(['guest', 'reservationRooms.roomType', 'reservationRooms.room']);

        $checkin = $reservation->arrival_date?->format('d M Y') ?? '-';
        $checkout = $reservation->departure_date?->format('d M Y') ?? '-';
        $nights = $reservation->arrival_date && $reservation->departure_date
            ? $reservation->arrival_date->diffInDays($reservation->departure_date)
            : 0;

        $rooms = $reservation->reservationRooms
            ->map(fn ($rr) => trim(($rr->roomType?->name ?? 'Room').($rr->room?->number ? ' ('.(string) $rr->room->number.')' : '')))
            ->unique()
            ->implode(', ');

        $lines = [
            "Hello {$reservation->guest?->full_name} 👋",
            '',
            'Your reservation at Pratasaba Resort has been confirmed:',
            '',
            "📋 Reservation: {$reservation->reservation_code}",
            "📅 Check-in:  {$checkin}",
            "📅 Check-out: {$checkout}",
            "🌙 Nights:     {$nights}",
        ];

        if ($rooms !== '') {
            $lines[] = "🛏️ Room(s):    {$rooms}";
        }

        $lines[] = "👥 Guests:     {$reservation->adults} adult(s)".($reservation->children > 0 ? ", {$reservation->children} child(ren)" : '');

        if ($reservation->special_requests) {
            $lines[] = "📝 Note:       {$reservation->special_requests}";
        }

        $lines = array_merge($lines, [
            '',
            'If you have any questions, simply reply to this message.',
            '',
            'We look forward to welcoming you! 🙏',
            'Pratasaba Resort',
        ]);

        return implode("\n", $lines);
    }
}
