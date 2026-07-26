<?php

namespace App\Telegram\Commands;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;

class WhoAmICommand extends BaseCommand
{
    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        $user = $tgUser->user;

        if ($user === null) {
            $this->reply($tgUser, 'Not linked. Use /link <code> to link your account.');

            return;
        }

        $roles = $user->getRoleNames()->implode(', ');
        $hotelLine = 'Hotel: Not set';

        if ($tgUser->hotel_id !== null) {
            $hotel = $tgUser->hotel;
            $hotelLine = $hotel
                ? "Hotel: {$hotel->name} ({$hotel->code})"
                : 'Hotel: Unknown';
        }

        $this->reply(
            $tgUser,
            "You are logged in as: {$user->name}\n".
            "Role(s): {$roles}\n".
            $hotelLine,
        );
    }
}
