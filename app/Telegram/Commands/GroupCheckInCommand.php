<?php

namespace App\Telegram\Commands;

use App\Actions\Groups\GroupCheckInAction;
use App\Models\ReservationGroup;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use InvalidArgumentException;

class GroupCheckInCommand extends BaseCommand
{
    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('groups.checkin') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'groups.checkin')) {
            return;
        }

        if (empty($args)) {
            $this->reply($tgUser, 'Usage: /groupcheckin <group_code>');

            return;
        }

        $this->setHotelContext($tgUser);

        $code = strtoupper($args[0]);

        $group = ReservationGroup::query()
            ->when($tgUser->hotel_id !== null, fn ($q) => $q->where('hotel_id', $tgUser->hotel_id))
            ->where('group_code', $code)
            ->first();

        if ($group === null) {
            $this->reply($tgUser, "❌ Group {$code} not found.");

            return;
        }

        try {
            $results = app(GroupCheckInAction::class)($group, $tgUser->user);
        } catch (InvalidArgumentException $e) {
            $this->reply($tgUser, "❌ {$e->getMessage()}");

            return;
        }

        $success = count($results['succeeded']);
        $failed = count($results['failed']);

        $message = "✅ Group check-in complete for {$code}.\n{$success} reservation(s) checked in.";

        if ($failed > 0) {
            $failures = collect($results['failed'])
                ->map(fn ($f) => "• {$f['reservation_code']}: {$f['reason']}")
                ->implode("\n");
            $message .= "\n\n⚠️ {$failed} failed:\n{$failures}";
        }

        $this->reply($tgUser, $message);
    }
}
