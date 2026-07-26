<?php

namespace App\Telegram\Commands;

use App\Models\Hotel;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;

class SwitchPropertyCommand extends BaseCommand
{
    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        $user = $tgUser->user;

        if ($user === null) {
            return;
        }

        if (empty($args)) {
            $hotels = $user->accessibleHotels()->get(['id', 'name', 'code']);

            if ($hotels->isEmpty()) {
                $this->reply($tgUser, 'No properties available.');

                return;
            }

            $buttons = $hotels->map(fn (Hotel $hotel) => [[
                'text' => "{$hotel->name} ({$hotel->code})",
                'callback_data' => "switch:{$hotel->code}",
            ]])->values()->all();

            $this->responder->sendInlineKeyboard(
                (int) $tgUser->chat_id,
                'Select a property:',
                $buttons,
            );

            return;
        }

        $this->switchToHotel($tgUser, strtoupper(trim($args[0])));
    }

    public function handleCallback(TelegramUser $tgUser, string $hotelCode): void
    {
        $this->switchToHotel($tgUser, strtoupper($hotelCode));
    }

    private function switchToHotel(TelegramUser $tgUser, string $hotelCode): void
    {
        $user = $tgUser->user;

        if ($user === null) {
            return;
        }

        $hotel = Hotel::query()
            ->where('code', $hotelCode)
            ->where('is_active', true)
            ->first();

        if ($hotel === null || ! $user->canAccessHotel($hotel->id)) {
            $this->reply($tgUser, "❌ Property '{$hotelCode}' not found or you don't have access.");

            return;
        }

        $tgUser->update(['hotel_id' => $hotel->id]);

        $this->reply($tgUser, "✅ Switched to {$hotel->name} ({$hotel->code}).");
    }
}
