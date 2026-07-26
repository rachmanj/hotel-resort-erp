<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Events\StockMovementRecorded;
use App\Models\InventoryItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    public function recordMovement(
        InventoryItem $item,
        StockMovementType $type,
        float $quantity,
        User $movedBy,
        ?Model $reference = null,
    ): StockMovement {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Movement quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($item, $type, $quantity, $movedBy, $reference): StockMovement {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);

            if ($type === StockMovementType::Adjustment) {
                throw new InvalidArgumentException('Use adjustStock() for stock adjustments.');
            }

            $delta = match ($type) {
                StockMovementType::In => $quantity,
                StockMovementType::Out, StockMovementType::Transfer => -$quantity,
            };

            $newStock = (float) $item->current_stock + $delta;

            if ($newStock < 0) {
                throw new InvalidArgumentException('Insufficient stock for this movement.');
            }

            $item->update(['current_stock' => $newStock]);

            $movement = StockMovement::query()->create([
                'inventory_item_id' => $item->id,
                'type' => $type->value,
                'quantity' => $quantity,
                'reference_type' => $reference !== null ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'moved_by' => $movedBy->id,
                'moved_at' => now(),
            ]);

            if (in_array($type, [StockMovementType::In, StockMovementType::Out], true)) {
                StockMovementRecorded::dispatch($movement);
            }

            return $movement;
        });
    }

    public function adjustStock(InventoryItem $item, float $newQuantity, User $adjustedBy, ?string $notes = null): StockMovement
    {
        if ($newQuantity < 0) {
            throw new InvalidArgumentException('Stock quantity cannot be negative.');
        }

        return DB::transaction(function () use ($item, $newQuantity, $adjustedBy): StockMovement {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->id);

            $movement = StockMovement::query()->create([
                'inventory_item_id' => $item->id,
                'type' => StockMovementType::Adjustment->value,
                'quantity' => $newQuantity,
                'reference_type' => null,
                'reference_id' => null,
                'moved_by' => $adjustedBy->id,
                'moved_at' => now(),
            ]);

            $item->update(['current_stock' => $newQuantity]);

            return $movement;
        });
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    public function getLowStockItems(?int $hotelId = null): Collection
    {
        $hotelId ??= session('current_hotel_id');

        return InventoryItem::query()
            ->when($hotelId !== null, function ($query) use ($hotelId): void {
                $query->where(function ($q) use ($hotelId): void {
                    $q->where('hotel_id', $hotelId)
                        ->orWhereNull('hotel_id');
                });
            })
            ->whereColumn('current_stock', '<=', 'reorder_level')
            ->orderBy('name')
            ->get();
    }
}
