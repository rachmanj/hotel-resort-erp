<?php

namespace App\Telegram\Commands;

use App\Enums\ReservationStatus;
use App\Models\Agent;
use App\Models\Reservation;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Telegram\TelegramResponder;

class AgentBookingsCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('agents.portal') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'agents.portal')) {
            return;
        }

        $this->setHotelContext($tgUser);

        $user = $tgUser->user;

        if ($user === null) {
            return;
        }

        $agent = Agent::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id);

                if ($user->agent_id !== null) {
                    $query->orWhere('id', $user->agent_id);
                }
            })
            ->where('is_active', true)
            ->first();

        if ($agent === null) {
            $this->reply($tgUser, '⛔ No active agent profile linked to your account.');

            return;
        }

        $bookings = Reservation::query()
            ->where('agent_id', $agent->id)
            ->with(['guest:id,full_name', 'reservationRooms.room:id,number'])
            ->whereNotIn('status', [ReservationStatus::Cancelled->value, ReservationStatus::NoShow->value])
            ->orderBy('arrival_date')
            ->limit(15)
            ->get();

        if ($bookings->isEmpty()) {
            $this->reply($tgUser, "📋 No active bookings for agent {$agent->name} ({$agent->code}).");

            return;
        }

        $lines = $bookings->map(function (Reservation $reservation) {
            $room = $reservation->reservationRooms->first();

            return sprintf(
                "%s · %s\n   %s → %s | Room %s | %s",
                $reservation->reservation_code,
                $reservation->status->label(),
                $reservation->arrival_date?->format('d M Y') ?? '-',
                $reservation->departure_date?->format('d M Y') ?? '-',
                $room?->room?->number ?? 'TBA',
                $reservation->guest?->full_name ?? 'Unknown',
            );
        })->implode("\n\n");

        $this->reply($tgUser, "📋 Agent Bookings · {$agent->name}\n\n{$lines}");
    }
}
