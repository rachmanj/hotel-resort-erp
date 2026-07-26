<?php

namespace App\Telegram\Commands;

use App\Models\TelegramUser;
use App\Telegram\Contracts\TelegramCommand;
use App\Telegram\TelegramResponder;

abstract class BaseCommand implements TelegramCommand
{
    public function __construct(
        protected TelegramResponder $responder,
    ) {}

    public function authorize(TelegramUser $tgUser): bool
    {
        return $tgUser->isLinked();
    }

    protected function reply(TelegramUser $tgUser, string $text, ?array $replyMarkup = null): void
    {
        if ($tgUser->chat_id === null) {
            return;
        }

        $this->responder->sendMessage((int) $tgUser->chat_id, $text, $replyMarkup);
    }

    protected function deny(TelegramUser $tgUser, string $permission): void
    {
        $this->reply($tgUser, "⛔ You don't have permission to use this command (requires: {$permission}).");
    }

    protected function requirePermission(TelegramUser $tgUser, string $permission): bool
    {
        $user = $tgUser->user;

        if ($user === null || ! $user->can($permission)) {
            $this->deny($tgUser, $permission);

            return false;
        }

        return true;
    }

    protected function setHotelContext(TelegramUser $tgUser): void
    {
        if ($tgUser->hotel_id !== null) {
            session(['current_hotel_id' => $tgUser->hotel_id]);
        }
    }

    protected function formatIdr(string|float $amount): string
    {
        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }
}
