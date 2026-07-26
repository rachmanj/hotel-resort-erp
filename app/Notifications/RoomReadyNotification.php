<?php

namespace App\Notifications;

use App\Models\Room;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RoomReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Room $room,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram(object $notifiable): string
    {
        return "✅ Room {$this->room->number} is now Vacant Clean & ready to sell.";
    }
}
