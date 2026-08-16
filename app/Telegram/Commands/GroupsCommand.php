<?php

namespace App\Telegram\Commands;

use App\Models\ReservationGroup;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;

class GroupsCommand extends BaseCommand
{
    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('groups.view') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'groups.view')) {
            return;
        }

        $this->setHotelContext($tgUser);

        $groups = ReservationGroup::query()
            ->with('picGuest:id,full_name')
            ->when($tgUser->hotel_id !== null, fn ($q) => $q->where('hotel_id', $tgUser->hotel_id))
            ->whereNotIn('status', ['checked_out', 'cancelled'])
            ->orderBy('arrival_date')
            ->limit(10)
            ->get();

        if ($groups->isEmpty()) {
            $this->reply($tgUser, 'No active group bookings found.');

            return;
        }

        $lines = $groups->map(function (ReservationGroup $group): string {
            $pic = $group->picGuest?->full_name ?? '–';
            $arrival = $group->arrival_date?->format('d M Y') ?? 'TBD';

            return "• {$group->group_code} · {$group->name}\n  {$group->status->label()} | {$arrival} | PIC: {$pic}";
        });

        $this->reply(
            $tgUser,
            "📋 Active Groups (up to 10):\n\n".$lines->implode("\n\n"),
        );
    }
}
