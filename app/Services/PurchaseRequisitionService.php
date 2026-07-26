<?php

namespace App\Services;

use App\Enums\PurchaseRequisitionStatus;
use App\Models\PurchaseRequisition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PurchaseRequisitionService
{
    /**
     * @param  array{department: string, notes?: string|null, items: list<array{inventory_item_id: int, quantity_requested: float}>}  $data
     */
    public function create(array $data, User $requester): PurchaseRequisition
    {
        if ($data['items'] === []) {
            throw new InvalidArgumentException('At least one item is required.');
        }

        return DB::transaction(function () use ($data, $requester): PurchaseRequisition {
            $requisition = PurchaseRequisition::query()->create([
                'requisition_no' => $this->generateRequisitionNumber(),
                'requested_by' => $requester->id,
                'department' => $data['department'],
                'status' => PurchaseRequisitionStatus::Draft->value,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $requisition->items()->create([
                    'inventory_item_id' => $item['inventory_item_id'],
                    'quantity_requested' => $item['quantity_requested'],
                ]);
            }

            return $requisition->load(['items.inventoryItem', 'requester']);
        });
    }

    public function submit(PurchaseRequisition $requisition): PurchaseRequisition
    {
        if ($requisition->status !== PurchaseRequisitionStatus::Draft) {
            throw new InvalidArgumentException('Only draft requisitions can be submitted.');
        }

        $requisition->update(['status' => PurchaseRequisitionStatus::PendingApproval->value]);

        return $requisition->fresh(['items.inventoryItem', 'requester']);
    }

    public function approve(PurchaseRequisition $requisition, User $approver): PurchaseRequisition
    {
        if ($requisition->status !== PurchaseRequisitionStatus::PendingApproval) {
            throw new InvalidArgumentException('Only pending requisitions can be approved.');
        }

        $requisition->update([
            'status' => PurchaseRequisitionStatus::Approved->value,
            'approved_by' => $approver->id,
        ]);

        return $requisition->fresh(['items.inventoryItem', 'requester', 'approver']);
    }

    private function generateRequisitionNumber(): string
    {
        $prefix = 'PR-'.now()->format('Y').'-';

        $last = PurchaseRequisition::query()
            ->withoutGlobalScope('hotel')
            ->where('requisition_no', 'like', $prefix.'%')
            ->orderByDesc('requisition_no')
            ->value('requisition_no');

        $sequence = $last !== null ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
