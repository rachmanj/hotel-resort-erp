<?php

namespace App\Telegram\Commands;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Services\InventoryService;
use App\Telegram\TelegramResponder;

class LowOrdersCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private InventoryService $inventoryService,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return $tgUser->isLinked() && (
            ($tgUser->user?->can('inventory.view') ?? false)
            || ($tgUser->user?->can('purchasing.view') ?? false)
        );
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        $this->setHotelContext($tgUser);

        $items = $this->inventoryService->getLowStockItems($tgUser->hotel_id);

        if ($items->isEmpty()) {
            $this->reply($tgUser, '✅ All inventory items are above reorder level.');

            return;
        }

        $lines = ["⚠️ *Low Stock Items*\n"];

        foreach ($items as $item) {
            $lines[] = "*{$item->name}*: {$item->current_stock} / {$item->reorder_level} {$item->unit->value}";
        }

        $this->reply($tgUser, implode("\n", $lines));
    }
}
