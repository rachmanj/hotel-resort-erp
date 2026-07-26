<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseRequisitionStatus;
use App\Enums\StockMovementType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseOrderService
{
    public function __construct(
        private InventoryService $inventoryService,
    ) {}

    /**
     * @param  array{supplier_id: int, expected_at?: string|null, items?: list<array{inventory_item_id: int, quantity_ordered: float, unit_cost: float}>}  $data
     */
    public function createFromRequisition(PurchaseRequisition $requisition, array $data): PurchaseOrder
    {
        if ($requisition->status !== PurchaseRequisitionStatus::Approved) {
            throw new InvalidArgumentException('Only approved requisitions can be converted to purchase orders.');
        }

        if ($requisition->purchaseOrder !== null) {
            throw new InvalidArgumentException('This requisition has already been converted.');
        }

        return DB::transaction(function () use ($requisition, $data): PurchaseOrder {
            $requisition->load('items');

            $orderItems = $data['items'] ?? $requisition->items->map(fn ($item) => [
                'inventory_item_id' => $item->inventory_item_id,
                'quantity_ordered' => (float) $item->quantity_requested,
                'unit_cost' => 0,
            ])->all();

            $totalAmount = collect($orderItems)->sum(
                fn (array $item) => (float) $item['quantity_ordered'] * (float) $item['unit_cost'],
            );

            $order = PurchaseOrder::query()->create([
                'purchase_requisition_id' => $requisition->id,
                'supplier_id' => $data['supplier_id'],
                'po_no' => $this->generatePoNumber(),
                'status' => PurchaseOrderStatus::Sent->value,
                'total_amount' => $totalAmount,
                'ordered_at' => now(),
                'expected_at' => $data['expected_at'] ?? null,
            ]);

            foreach ($orderItems as $item) {
                $order->items()->create([
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'unit_cost' => $item['unit_cost'],
                    'quantity_received' => 0,
                ]);
            }

            $requisition->update(['status' => PurchaseRequisitionStatus::Converted->value]);

            return $order->load(['items.inventoryItem', 'supplier', 'purchaseRequisition']);
        });
    }

    /**
     * @param  list<array{purchase_order_item_id: int, quantity_received: float}>  $receivedItems
     */
    public function markReceived(PurchaseOrder $order, array $receivedItems, User $receivedBy): PurchaseOrder
    {
        if (in_array($order->status, [PurchaseOrderStatus::Received, PurchaseOrderStatus::Cancelled], true)) {
            throw new InvalidArgumentException('This purchase order cannot receive stock.');
        }

        return DB::transaction(function () use ($order, $receivedItems, $receivedBy): PurchaseOrder {
            $order->load('items');

            foreach ($receivedItems as $received) {
                $line = $order->items->firstWhere('id', $received['purchase_order_item_id']);

                if ($line === null) {
                    throw new InvalidArgumentException('Invalid purchase order line item.');
                }

                $qty = (float) $received['quantity_received'];

                if ($qty <= 0) {
                    continue;
                }

                $remaining = (float) $line->quantity_ordered - (float) $line->quantity_received;

                if ($qty > $remaining) {
                    throw new InvalidArgumentException("Cannot receive more than ordered for {$line->inventoryItem?->name}.");
                }

                $line->update([
                    'quantity_received' => (float) $line->quantity_received + $qty,
                ]);

                $line->loadMissing('inventoryItem');

                $this->inventoryService->recordMovement(
                    $line->inventoryItem,
                    StockMovementType::In,
                    $qty,
                    $receivedBy,
                    $order,
                );
            }

            $order->refresh()->load('items');

            $allReceived = $order->items->every(fn ($item) => $item->isFullyReceived());
            $anyReceived = $order->items->contains(fn ($item) => (float) $item->quantity_received > 0);

            $status = match (true) {
                $allReceived => PurchaseOrderStatus::Received,
                $anyReceived => PurchaseOrderStatus::PartiallyReceived,
                default => $order->status,
            };

            $order->update(['status' => $status->value]);

            return $order->fresh(['items.inventoryItem', 'supplier']);
        });
    }

    private function generatePoNumber(): string
    {
        $prefix = 'PO-'.now()->format('Y').'-';

        $last = PurchaseOrder::query()
            ->withoutGlobalScope('hotel')
            ->where('po_no', 'like', $prefix.'%')
            ->orderByDesc('po_no')
            ->value('po_no');

        $sequence = $last !== null ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
