<?php

namespace App\Http\Controllers;

use App\Enums\WorkOrderStatus;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Http\Requests\UpdateWorkOrderRequest;
use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkOrderController extends Controller
{
    public function __construct(
        private MaintenanceService $maintenanceService,
    ) {}

    public function index(Request $request): Response
    {
        $workOrders = WorkOrder::query()
            ->with(['assignee:id,name', 'asset:id,name', 'maintenanceRequest.room:id,number'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->user()?->hasRole('maintenance') && $request->string('scope')->toString() === 'mine', function ($q) use ($request): void {
                $q->where('assigned_to', $request->user()->id);
            })
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (WorkOrder $wo) => [
                'id' => $wo->id,
                'description' => $wo->description,
                'status' => $wo->status->value,
                'status_label' => $wo->status->label(),
                'cost' => $wo->cost !== null ? (float) $wo->cost : null,
                'assignee' => $wo->assignee?->only(['id', 'name']),
                'asset' => $wo->asset?->only(['id', 'name']),
                'room_number' => $wo->maintenanceRequest?->room?->number,
                'completed_at' => $wo->completed_at?->toDateTimeString(),
                'created_at' => $wo->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Maintenance/WorkOrders/Index', [
            'workOrders' => $workOrders,
            'filters' => $request->only(['status', 'scope']),
            'statusOptions' => collect(WorkOrderStatus::cases())->map(fn (WorkOrderStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'technicians' => User::query()->role('maintenance')->orderBy('name')->get(['id', 'name']),
            'openRequests' => MaintenanceRequest::query()
                ->whereNotIn('status', ['resolved', 'closed'])
                ->with('room:id,number')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(['id', 'description', 'room_id', 'priority']),
            'assets' => Asset::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreWorkOrderRequest $request): RedirectResponse
    {
        $this->maintenanceService->createWorkOrder($request->validated());

        return back()->with('success', 'Work order created.');
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $workOrder->update($request->validated());

        return back()->with('success', 'Work order updated.');
    }

    public function complete(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        abort_unless($request->user()?->can('maintenance.manage'), 403);

        $request->validate([
            'cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->maintenanceService->completeWorkOrder(
            $workOrder,
            $request->has('cost') ? (float) $request->input('cost') : null,
        );

        return back()->with('success', 'Work order completed.');
    }
}
