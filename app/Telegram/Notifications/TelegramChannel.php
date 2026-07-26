<?php

namespace App\Telegram\Notifications;

use App\Telegram\TelegramResponder;
use Illuminate\Notifications\Notification;

class TelegramChannel
{
    public function __construct(
        private TelegramResponder $responder,
    ) {}

    /**
     * @param  mixed  $notifiable
     */
    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        $message = $notification->toTelegram($notifiable);

        if ($message === null) {
            return;
        }

        $chatId = $notifiable->routeNotificationForTelegram();

        if ($chatId === null) {
            return;
        }

        $this->responder->sendMessage((int) $chatId, $message);
    }
}
