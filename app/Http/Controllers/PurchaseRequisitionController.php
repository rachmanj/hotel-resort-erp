<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseRequisitionStatus;
use App\Http\Requests\StorePurchaseRequisitionRequest;
use App\Models\InventoryItem;
use App\Models\PurchaseRequisition;
use App\Services\PurchaseRequisitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseRequisitionController extends Controller
{
    public function __construct(
        private PurchaseRequisitionService $requisitionService,
    ) {}

    public function index(Request $request): Response
    {
        $requisitions = PurchaseRequisition::query()
            ->with(['requester:id,name', 'approver:id,name'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PurchaseRequisition $req) => [
                'id' => $req->id,
                'requisition_no' => $req->requisition_no,
                'department' => $req->department,
                'status' => $req->status->value,
                'status_label' => $req->status->label(),
                'requested_by' => $req->requester?->only(['id', 'name']),
                'approved_by' => $req->approver?->only(['id', 'name']),
                'items_count' => $req->items_count,
                'created_at' => $req->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Purchasing/Requisitions/Index', [
            'requisitions' => $requisitions,
            'filters' => $request->only(['status']),
            'statusOptions' => collect(PurchaseRequisitionStatus::cases())->map(fn (PurchaseRequisitionStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'inventoryItems' => InventoryItem::query()->orderBy('name')->get(['id', 'name', 'unit', 'current_stock']),
            'canApprove' => $request->user()?->can('purchasing.approve') ?? false,
        ]);
    }

    public function show(PurchaseRequisition $purchaseRequisition): Response
    {
        $purchaseRequisition->load(['items.inventoryItem', 'requester:id,name', 'approver:id,name']);

        return Inertia::render('Purchasing/Requisitions/Show', [
            'requisition' => [
                'id' => $purchaseRequisition->id,
                'requisition_no' => $purchaseRequisition->requisition_no,
                'department' => $purchaseRequisition->department,
                'status' => $purchaseRequisition->status->value,
                'status_label' => $purchaseRequisition->status->label(),
                'notes' => $purchaseRequisition->notes,
                'requested_by' => $purchaseRequisition->requester?->only(['id', 'name']),
                'approved_by' => $purchaseRequisition->approver?->only(['id', 'name']),
                'items' => $purchaseRequisition->items->map(fn ($item) => [
                    'id' => $item->id,
                    'inventory_item' => $item->inventoryItem?->only(['id', 'name', 'unit']),
                    'quantity_requested' => (float) $item->quantity_requested,
                ]),
                'created_at' => $purchaseRequisition->created_at?->toDateTimeString(),
            ],
            'canApprove' => request()->user()?->can('purchasing.approve') ?? false,
        ]);
    }

    public function store(StorePurchaseRequisitionRequest $request): RedirectResponse
    {
        $requisition = $this->requisitionService->create($request->validated(), $request->user());

        return redirect()
            ->route('requisitions.show', $requisition)
            ->with('success', 'Purchase requisition created.');
    }

    public function submit(PurchaseRequisition $purchaseRequisition): RedirectResponse
    {
        $this->requisitionService->submit($purchaseRequisition);

        return back()->with('success', 'Requisition submitted for approval.');
    }

    public function approve(PurchaseRequisition $purchaseRequisition): RedirectResponse
    {
        abort_unless(request()->user()?->can('purchasing.approve'), 403);

        $this->requisitionService->approve($purchaseRequisition, request()->user());

        return back()->with('success', 'Requisition approved.');
    }
}
