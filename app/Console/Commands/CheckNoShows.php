<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\TelegramUser;
use App\Models\User;
use App\Notifications\NoShowAlertNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class CheckNoShows extends Command
{
    protected $signature = 'hotel:check-no-shows';

    protected $description = 'Check for reservation no-shows and send Telegram alerts';

    public function handle(): int
    {
        $today = now()->toDateString();

        $reservations = Reservation::query()
            ->withoutGlobalScope('hotel')
            ->with('guest')
            ->whereIn('status', [
                ReservationStatus::Tentative->value,
                ReservationStatus::Confirmed->value,
            ])
            ->where('arrival_date', $today)
            ->get();

        if ($reservations->isEmpty()) {
            $this->info('No pending arrivals today.');

            return self::SUCCESS;
        }

        $recipients = $this->getAlertRecipients();

        foreach ($reservations as $reservation) {
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new NoShowAlertNotification($reservation));
            }

            $pastCutoff = now()->toDateString() > $reservation->arrival_date->toDateString()
                || now()->format('H:i') >= '23:59';

            if ($pastCutoff) {
                $reservation->update(['status' => ReservationStatus::NoShow->value]);
                $this->info("Marked {$reservation->reservation_code} as no-show.");
            } else {
                $this->info("Alert sent for {$reservation->reservation_code}.");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, TelegramUser>
     */
    private function getAlertRecipients()
    {
        $userIds = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['front_office', 'manager']))
            ->pluck('id');

        return TelegramUser::query()
            ->withoutGlobalScope('hotel')
            ->whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->whereNotNull('chat_id')
            ->whereNotNull('linked_at')
            ->get();
    }
}
