<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
    ) {
        $this->order->loadMissing(['items.menuItem', 'restaurantTable', 'openedBy:id,name']);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('kitchen.'.$this->order->hotel_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'order_no' => $this->order->order_no,
                'order_type' => $this->order->order_type->value,
                'order_type_label' => $this->order->order_type->label(),
                'status' => $this->order->status->value,
                'status_label' => $this->order->status->label(),
                'total_amount' => (float) $this->order->total_amount,
                'table' => $this->order->restaurantTable?->name,
                'opened_by' => $this->order->openedBy?->name,
                'items' => $this->order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->menuItem?->name,
                    'quantity' => $item->quantity,
                    'notes' => $item->notes,
                    'status' => $item->status->value,
                ])->values()->all(),
                'updated_at' => $this->order->updated_at?->toIso8601String(),
            ],
        ];
    }
}
