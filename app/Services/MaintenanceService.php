<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceReportedVia;
use App\Enums\MaintenanceRequestStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\Room;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MaintenanceService
{
    /**
     * @param  array{room_id?: int|null, asset_id?: int|null, description: string, priority?: string, reported_via?: string}  $data
     */
    public function createRequest(array $data, User $reporter): MaintenanceRequest
    {
        if (empty($data['room_id']) && empty($data['asset_id'])) {
            throw new InvalidArgumentException('A room or asset must be specified.');
        }

        return MaintenanceRequest::query()->create([
            'room_id' => $data['room_id'] ?? null,
            'asset_id' => $data['asset_id'] ?? null,
            'reported_by' => $reporter->id,
            'reported_via' => $data['reported_via'] ?? MaintenanceReportedVia::Web->value,
            'priority' => $data['priority'] ?? MaintenancePriority::Medium->value,
            'description' => $data['description'],
            'status' => MaintenanceRequestStatus::Open->value,
        ]);
    }

    public function createRequestForRoom(
        Room $room,
        string $description,
        User $reporter,
        MaintenanceReportedVia $via = MaintenanceReportedVia::Web,
        MaintenancePriority $priority = MaintenancePriority::Medium,
    ): MaintenanceRequest {
        return $this->createRequest([
            'room_id' => $room->id,
            'description' => $description,
            'reported_via' => $via->value,
            'priority' => $priority->value,
        ], $reporter);
    }

    public function assign(MaintenanceRequest $request, User $assignee): MaintenanceRequest
    {
        if (in_array($request->status, [MaintenanceRequestStatus::Resolved, MaintenanceRequestStatus::Closed], true)) {
            throw new InvalidArgumentException('Cannot assign a resolved or closed request.');
        }

        $request->update([
            'assigned_to' => $assignee->id,
            'status' => MaintenanceRequestStatus::Assigned->value,
        ]);

        return $request->fresh(['room', 'asset', 'assignee', 'reporter']);
    }

    public function resolve(MaintenanceRequest $request): MaintenanceRequest
    {
        if (in_array($request->status, [MaintenanceRequestStatus::Resolved, MaintenanceRequestStatus::Closed], true)) {
            throw new InvalidArgumentException('Request is already resolved or closed.');
        }

        $request->update([
            'status' => MaintenanceRequestStatus::Resolved->value,
            'resolved_at' => now(),
        ]);

        return $request->fresh(['room', 'asset', 'assignee', 'reporter']);
    }

    /**
     * @param  array{maintenance_request_id?: int|null, asset_id?: int|null, assigned_to: int, description: string}  $data
     */
    public function createWorkOrder(array $data): WorkOrder
    {
        if (empty($data['maintenance_request_id']) && empty($data['asset_id'])) {
            throw new InvalidArgumentException('A maintenance request or asset must be specified.');
        }

        $workOrder = WorkOrder::query()->create([
            'maintenance_request_id' => $data['maintenance_request_id'] ?? null,
            'asset_id' => $data['asset_id'] ?? null,
            'assigned_to' => $data['assigned_to'],
            'description' => $data['description'],
            'status' => WorkOrderStatus::Open->value,
        ]);

        if ($workOrder->maintenance_request_id !== null) {
            $request = MaintenanceRequest::query()->find($workOrder->maintenance_request_id);

            if ($request !== null && $request->status === MaintenanceRequestStatus::Open) {
                $request->update([
                    'assigned_to' => $data['assigned_to'],
                    'status' => MaintenanceRequestStatus::InProgress->value,
                ]);
            }
        }

        if ($workOrder->asset_id !== null) {
            Asset::query()
                ->where('id', $workOrder->asset_id)
                ->update(['status' => AssetStatus::UnderMaintenance->value]);
        }

        return $workOrder->load(['assignee', 'asset', 'maintenanceRequest']);
    }

    public function completeWorkOrder(WorkOrder $workOrder, ?float $cost = null): WorkOrder
    {
        if ($workOrder->status === WorkOrderStatus::Completed) {
            throw new InvalidArgumentException('Work order is already completed.');
        }

        return DB::transaction(function () use ($workOrder, $cost): WorkOrder {
            $workOrder->update([
                'status' => WorkOrderStatus::Completed->value,
                'completed_at' => now(),
                'cost' => $cost,
            ]);

            if ($workOrder->asset_id !== null) {
                Asset::query()
                    ->where('id', $workOrder->asset_id)
                    ->update(['status' => AssetStatus::Operational->value]);
            }

            if ($workOrder->maintenance_request_id !== null) {
                $request = MaintenanceRequest::query()->find($workOrder->maintenance_request_id);

                if ($request !== null && ! in_array($request->status, [MaintenanceRequestStatus::Resolved, MaintenanceRequestStatus::Closed], true)) {
                    $request->update([
                        'status' => MaintenanceRequestStatus::Resolved->value,
                        'resolved_at' => now(),
                    ]);
                }
            }

            return $workOrder->fresh(['assignee', 'asset', 'maintenanceRequest']);
        });
    }
}
