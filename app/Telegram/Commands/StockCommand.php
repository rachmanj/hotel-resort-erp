<?php

namespace App\Telegram\Commands;

use App\Models\InventoryItem;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;

class StockCommand extends BaseCommand
{
    public function authorize(TelegramUser $tgUser): bool
    {
        return $tgUser->isLinked() && ($tgUser->user?->can('inventory.view') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        $this->setHotelContext($tgUser);

        if ($args === []) {
            $this->reply($tgUser, 'Usage: /stock {item_name}');

            return;
        }

        $search = implode(' ', $args);

        $items = InventoryItem::query()
            ->where('name', 'like', "%{$search}%")
            ->orderBy('name')
            ->limit(10)
            ->get();

        if ($items->isEmpty()) {
            $this->reply($tgUser, "📦 No inventory items matching \"{$search}\".");

            return;
        }

        $lines = ["📦 *Stock Levels*\n"];

        foreach ($items as $item) {
            $low = $item->isLowStock() ? ' ⚠️ LOW' : '';
            $lines[] = "*{$item->name}*: {$item->current_stock} {$item->unit->value}{$low}";
            $lines[] = "Reorder level: {$item->reorder_level}";
            $lines[] = '';
        }

        $this->reply($tgUser, implode("\n", $lines));
    }
}
