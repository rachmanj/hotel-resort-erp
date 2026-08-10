<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private PurchaseOrderService $purchaseOrderService,
    ) {}

    public function index(Request $request): Response
    {
        $orders = PurchaseOrder::query()
            ->with(['supplier:id,name', 'purchaseRequisition:id,requisition_no'])
            ->withCount('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'po_no' => $order->po_no,
                'status' => $order->status->value,
                'status_label' => $order->status->label(),
                'total_amount' => (float) $order->total_amount,
                'supplier' => $order->supplier?->only(['id', 'name']),
                'requisition_no' => $order->purchaseRequisition?->requisition_no,
                'ordered_at' => $order->ordered_at?->toDateTimeString(),
                'expected_at' => $order->expected_at?->toDateTimeString(),
                'items_count' => $order->items_count,
            ]);

        return Inertia::render('Purchasing/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status']),
            'statusOptions' => collect(PurchaseOrderStatus::cases())->map(fn (PurchaseOrderStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'approvedRequisitions' => PurchaseRequisition::query()
                ->where('status', 'approved')
                ->whereDoesntHave('purchaseOrder')
                ->with('items.inventoryItem:id,name')
                ->orderByDesc('created_at')
                ->get(['id', 'requisition_no', 'department']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Purchasing/Orders/Create', [
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'approvedRequisitions' => PurchaseRequisition::query()
                ->where('status', 'approved')
                ->whereDoesntHave('purchaseOrder')
                ->with('items.inventoryItem:id,name')
                ->orderByDesc('created_at')
                ->get(['id', 'requisition_no', 'department']),
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): Response
    {
        $purchaseOrder->load(['items.inventoryItem', 'supplier', 'purchaseRequisition']);

        return Inertia::render('Purchasing/Orders/Show', [
            'order' => [
                'id' => $purchaseOrder->id,
                'po_no' => $purchaseOrder->po_no,
                'status' => $purchaseOrder->status->value,
                'status_label' => $purchaseOrder->status->label(),
                'total_amount' => (float) $purchaseOrder->total_amount,
                'supplier' => $purchaseOrder->supplier?->only(['id', 'name']),
                'requisition_no' => $purchaseOrder->purchaseRequisition?->requisition_no,
                'ordered_at' => $purchaseOrder->ordered_at?->toDateTimeString(),
                'expected_at' => $purchaseOrder->expected_at?->toDateTimeString(),
                'items' => $purchaseOrder->items->map(fn ($item) => [
                    'id' => $item->id,
                    'inventory_item' => $item->inventoryItem?->only(['id', 'name', 'unit']),
                    'quantity_ordered' => (float) $item->quantity_ordered,
                    'quantity_received' => (float) $item->quantity_received,
                    'unit_cost' => (float) $item->unit_cost,
                ]),
            ],
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $requisition = PurchaseRequisition::query()->findOrFail($request->integer('purchase_requisition_id'));

        $order = $this->purchaseOrderService->createFromRequisition($requisition, $request->validated());

        return redirect()
            ->route('purchase-orders.show', $order)
            ->with('success', 'Purchase order created.');
    }

    public function receive(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0'],
        ]);

        $this->purchaseOrderService->markReceived(
            $purchaseOrder,
            $request->input('items'),
            $request->user(),
        );

        return back()->with('success', 'Stock received.');
    }
}
