<?php

namespace App\WhatsApp\Notifications;

use App\WhatsApp\WhatsAppResponder;
use Illuminate\Notifications\Notification;

class WhatsAppChannel
{
    public function __construct(
        private WhatsAppResponder $responder,
    ) {}

    /**
     * @param  mixed  $notifiable
     */
    public function send($notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        if ($message === null) {
            return;
        }

        $phone = $notifiable->routeNotificationForWhatsApp();

        if ($phone === null) {
            return;
        }

        try {
            $this->responder->sendText($phone, $message);
        } catch (\Throwable $e) {
            // Kegagalan kirim WA tidak boleh menggagalkan proses utama (mis. pembuatan reservasi).
            report($e);
        }
    }
}
