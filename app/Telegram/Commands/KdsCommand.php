<?php

namespace App\Telegram\Commands;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\OrderService;
use App\Telegram\TelegramResponder;

class KdsCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private OrderService $orderService,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return $tgUser->isLinked() && ($tgUser->user?->can('fb.view') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        $this->setHotelContext($tgUser);

        $orders = $this->orderService->getActiveKitchenOrders();

        if ($orders->isEmpty()) {
            $this->reply($tgUser, '🍳 No active kitchen orders.');

            return;
        }

        $lines = ["🍳 *Kitchen Display Summary*\n"];

        foreach ($orders as $order) {
            $table = $order->restaurantTable?->name ?? ($order->reservation?->guest?->full_name ?? '—');
            $items = $order->items->map(fn ($i) => "  • {$i->quantity}x {$i->menuItem?->name}")->implode("\n");

            $lines[] = "*{$order->order_no}* [{$order->status->label()}]";
            $lines[] = "Type: {$order->order_type->label()} | Table/Guest: {$table}";
            $lines[] = $items;
            $lines[] = 'Total: '.$this->formatIdr($order->total_amount);
            $lines[] = '';
        }

        $this->reply($tgUser, implode("\n", $lines));
    }
}
