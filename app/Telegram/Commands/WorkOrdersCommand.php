<?php

namespace App\Telegram\Commands;

use App\Enums\WorkOrderStatus;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Models\WorkOrder;

class WorkOrdersCommand extends BaseCommand
{
    public function authorize(TelegramUser $tgUser): bool
    {
        return $tgUser->isLinked() && ($tgUser->user?->can('maintenance.view') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        $this->setHotelContext($tgUser);

        $scope = strtolower($args[0] ?? 'open');

        $query = WorkOrder::query()
            ->with(['assignee:id,name', 'maintenanceRequest.room:id,number', 'asset:id,name'])
            ->orderByDesc('created_at');

        if ($scope === 'mine') {
            $query->where('assigned_to', $tgUser->user_id);
        } else {
            $query->whereIn('status', [
                WorkOrderStatus::Open->value,
                WorkOrderStatus::InProgress->value,
            ]);
        }

        $workOrders = $query->limit(15)->get();

        if ($workOrders->isEmpty()) {
            $this->reply($tgUser, '🔧 No work orders found.');

            return;
        }

        $lines = ["🔧 *Work Orders*\n"];

        foreach ($workOrders as $workOrder) {
            $location = $workOrder->maintenanceRequest?->room?->number
                ? 'Room '.$workOrder->maintenanceRequest->room->number
                : ($workOrder->asset?->name ?? '–');

            $lines[] = "*#{$workOrder->id}* [{$workOrder->status->label()}]";
            $lines[] = "Location: {$location}";
            $lines[] = "Assigned: {$workOrder->assignee?->name}";
            $lines[] = mb_substr($workOrder->description, 0, 80);
            $lines[] = '';
        }

        $this->reply($tgUser, implode("\n", $lines));
    }
}
