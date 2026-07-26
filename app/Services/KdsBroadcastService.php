<?php

namespace App\Services;

use App\Events\OrderStatusChanged;
use App\Models\Order;

class KdsBroadcastService
{
    public function broadcastOrderUpdate(Order $order): void
    {
        event(new OrderStatusChanged($order));
    }
}
