<?php

namespace App\Console\Commands;

use App\Models\TelegramUser;
use App\Models\User;
use App\Services\InventoryService;
use App\Telegram\TelegramResponder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CheckLowStockCommand extends Command
{
    protected $signature = 'inventory:check-low-stock';

    protected $description = 'Check for low stock inventory items and alert via logging';

    public function handle(InventoryService $inventoryService): int
    {
        $items = $inventoryService->getLowStockItems(null);

        if ($items->isEmpty()) {
            $this->info('No low stock items.');

            return self::SUCCESS;
        }

        $names = $items->map(fn ($item) => "{$item->name} ({$item->current_stock}/{$item->reorder_level})")->implode(', ');

        Log::warning('Low stock alert', [
            'count' => $items->count(),
            'items' => $items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'current_stock' => (float) $item->current_stock,
                'reorder_level' => (float) $item->reorder_level,
                'hotel_id' => $item->hotel_id,
            ])->all(),
        ]);

        $this->warn("Low stock ({$items->count()}): {$names}");

        $recipients = $this->getAlertRecipients();

        if ($recipients->isNotEmpty()) {
            $message = "⚠️ Low stock alert ({$items->count()} items):\n".
                $items->map(fn ($item) => "• {$item->name}: {$item->current_stock}/{$item->reorder_level} {$item->unit->value}")->implode("\n");

            $responder = app(TelegramResponder::class);

            foreach ($recipients as $recipient) {
                if ($recipient->chat_id !== null) {
                    $responder->sendMessage((int) $recipient->chat_id, $message);
                }
            }

            $this->info("Telegram alerts sent to {$recipients->count()} recipients.");
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, TelegramUser>
     */
    private function getAlertRecipients(): Collection
    {
        $userIds = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['finance', 'manager', 'admin']))
            ->pluck('id');

        return TelegramUser::query()
            ->withoutGlobalScope('hotel')
            ->whereIn('user_id', $userIds)
            ->where('is_active', true)
            ->whereNotNull('chat_id')
            ->whereNotNull('linked_at')
            ->get();
    }
}
