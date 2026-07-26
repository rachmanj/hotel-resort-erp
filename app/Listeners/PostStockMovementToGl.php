<?php

namespace App\Listeners;

use App\Enums\StockMovementType;
use App\Events\StockMovementRecorded;
use App\Services\Accounting\GlPostingService;

class PostStockMovementToGl
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function handle(StockMovementRecorded $event): void
    {
        $movement = $event->stockMovement->loadMissing('inventoryItem.hotel');
        $item = $movement->inventoryItem;

        if ($item === null) {
            return;
        }

        $hotelId = (int) ($item->hotel_id ?? session('current_hotel_id'));

        if ($hotelId === 0) {
            return;
        }

        $quantity = round((float) $movement->quantity, 2);

        if ($quantity <= 0) {
            return;
        }

        $unitCost = 1000.0;
        $amount = round($quantity * $unitCost, 2);

        if ($amount <= 0) {
            return;
        }

        $transactionDate = ($movement->moved_at ?? now())->toDateString();
        $sourceType = 'stock_movement';
        $sourceId = $movement->id;
        $reference = "SM-{$movement->id}";
        $description = "Stock {$movement->type->value}: {$item->name}";

        $inventory = $this->glPostingService->findAccountByCode($hotelId, '1-1500');
        $cogs = $this->glPostingService->findAccountByCode($hotelId, '5-1100');
        $stockAdjustment = $this->glPostingService->findAccountByCode($hotelId, '6-8500');

        $lines = match ($movement->type) {
            StockMovementType::In => [
                $this->line($hotelId, $inventory->id, $transactionDate, $amount, 0, $description, $reference, $sourceType, $sourceId),
                $this->line($hotelId, $stockAdjustment->id, $transactionDate, 0, $amount, $description, $reference, $sourceType, $sourceId),
            ],
            StockMovementType::Out => [
                $this->line($hotelId, $cogs->id, $transactionDate, $amount, 0, $description, $reference, $sourceType, $sourceId),
                $this->line($hotelId, $inventory->id, $transactionDate, 0, $amount, $description, $reference, $sourceType, $sourceId),
            ],
            default => [],
        };

        if ($lines === []) {
            return;
        }

        $this->glPostingService->post($lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function line(
        int $hotelId,
        int $accountId,
        string $transactionDate,
        float $debit,
        float $credit,
        string $description,
        string $reference,
        string $sourceType,
        int $sourceId,
    ): array {
        return [
            'hotel_id' => $hotelId,
            'chart_of_account_id' => $accountId,
            'transaction_date' => $transactionDate,
            'debit' => $debit,
            'credit' => $credit,
            'description' => $description,
            'reference_number' => $reference,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ];
    }
}
