<?php

namespace App\Telegram\Commands;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;

class LinkCommand extends BaseCommand
{
    public function authorize(TelegramUser $tgUser): bool
    {
        return true;
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (empty($args)) {
            $this->reply($tgUser, 'Usage: /link <code>');

            return;
        }

        $code = strtoupper(trim($args[0]));

        $pending = TelegramUser::query()
            ->withoutGlobalScope('hotel')
            ->where('link_code', $code)
            ->where('link_code_expires_at', '>', now())
            ->whereNotNull('user_id')
            ->first();

        if ($pending === null) {
            $this->reply($tgUser, '❌ Invalid or expired link code. Generate a new one from your web profile.');

            return;
        }

        $user = $pending->user;

        if ($user === null) {
            $this->reply($tgUser, '❌ Invalid link code.');

            return;
        }

        $hotelId = $user->hotel_id;
        $pendingId = $pending->id;

        if ($pendingId !== $tgUser->id) {
            $pending->update([
                'user_id' => null,
                'link_code' => null,
                'link_code_expires_at' => null,
            ]);
        }

        $tgUser->update([
            'user_id' => $user->id,
            'hotel_id' => $hotelId,
            'linked_at' => now(),
            'link_code' => null,
            'link_code_expires_at' => null,
            'is_active' => true,
        ]);

        if ($pendingId !== $tgUser->id && $pending->chat_id === null) {
            $pending->delete();
        }

        $this->reply($tgUser, "✅ Account linked! You are now logged in as {$user->name}.");
    }
}
