<?php

namespace App\Http\Controllers;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceRequestStatus;
use App\Http\Requests\StoreMaintenanceRequestRequest;
use App\Http\Requests\UpdateMaintenanceRequestRequest;
use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\Room;
use App\Models\User;
use App\Services\MaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceRequestController extends Controller
{
    public function __construct(
        private MaintenanceService $maintenanceService,
    ) {}

    public function index(Request $request): Response
    {
        $requests = MaintenanceRequest::query()
            ->with(['room:id,number', 'asset:id,name', 'reporter:id,name', 'assignee:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->string('priority')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MaintenanceRequest $mr) => [
                'id' => $mr->id,
                'description' => $mr->description,
                'status' => $mr->status->value,
                'status_label' => $mr->status->label(),
                'priority' => $mr->priority->value,
                'priority_label' => $mr->priority->label(),
                'reported_via' => $mr->reported_via->value,
                'room' => $mr->room?->only(['id', 'number']),
                'asset' => $mr->asset?->only(['id', 'name']),
                'reporter' => $mr->reporter?->only(['id', 'name']),
                'assignee' => $mr->assignee?->only(['id', 'name']),
                'resolved_at' => $mr->resolved_at?->toDateTimeString(),
                'created_at' => $mr->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Maintenance/Requests/Index', [
            'requests' => $requests,
            'filters' => $request->only(['status', 'priority']),
            'statusOptions' => collect(MaintenanceRequestStatus::cases())->map(fn (MaintenanceRequestStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'priorityOptions' => collect(MaintenancePriority::cases())->map(fn (MaintenancePriority $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
            'rooms' => Room::query()->orderBy('number')->get(['id', 'number']),
            'assets' => Asset::query()->orderBy('name')->get(['id', 'name']),
            'technicians' => User::query()->role('maintenance')->orderBy('name')->get(['id', 'name']),
            'canManage' => $request->user()?->can('maintenance.manage') ?? false,
            'canCreate' => $request->user()?->can('maintenance.create') ?? false,
        ]);
    }

    public function store(StoreMaintenanceRequestRequest $request): RedirectResponse
    {
        $this->maintenanceService->createRequest($request->validated(), $request->user());

        return back()->with('success', 'Maintenance request created.');
    }

    public function update(UpdateMaintenanceRequestRequest $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $data = $request->validated();

        if (isset($data['assigned_to'])) {
            $assignee = User::query()->findOrFail($data['assigned_to']);
            $this->maintenanceService->assign($maintenanceRequest, $assignee);
            unset($data['assigned_to']);
        }

        if ($data !== []) {
            $maintenanceRequest->update($data);
        }

        return back()->with('success', 'Maintenance request updated.');
    }

    public function resolve(MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        abort_unless(request()->user()?->can('maintenance.manage'), 403);

        $this->maintenanceService->resolve($maintenanceRequest);

        return back()->with('success', 'Maintenance request resolved.');
    }
}
