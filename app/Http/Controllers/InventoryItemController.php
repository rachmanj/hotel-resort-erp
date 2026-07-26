<?php

namespace App\Http\Controllers;

use App\Enums\InventoryCategory;
use App\Enums\InventoryUnit;
use App\Http\Requests\StoreInventoryItemRequest;
use App\Http\Requests\UpdateInventoryItemRequest;
use App\Models\InventoryItem;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryItemController extends Controller
{
    public function __construct(
        private InventoryService $inventoryService,
    ) {}

    public function index(Request $request): Response
    {
        $items = InventoryItem::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $search = $request->string('search')->toString();
                $q->where('name', 'like', "%{$search}%");
            })
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('current_stock', '<=', 'reorder_level'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (InventoryItem $item) => $this->formatItem($item));

        return Inertia::render('Inventory/Index', [
            'items' => $items,
            'filters' => $request->only(['search', 'category', 'low_stock']),
            'categoryOptions' => collect(InventoryCategory::cases())->map(fn (InventoryCategory $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'unitOptions' => collect(InventoryUnit::cases())->map(fn (InventoryUnit $u) => [
                'value' => $u->value,
                'label' => $u->label(),
            ]),
            'lowStockCount' => $this->inventoryService->getLowStockItems()->count(),
        ]);
    }

    public function store(StoreInventoryItemRequest $request): RedirectResponse
    {
        InventoryItem::query()->create($request->validated());

        return back()->with('success', 'Inventory item created.');
    }

    public function update(UpdateInventoryItemRequest $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $inventoryItem->update($request->validated());

        return back()->with('success', 'Inventory item updated.');
    }

    public function adjust(Request $request, InventoryItem $inventoryItem): RedirectResponse
    {
        $request->validate([
            'quantity' => ['required', 'numeric', 'min:0'],
        ]);

        $this->inventoryService->adjustStock(
            $inventoryItem,
            (float) $request->input('quantity'),
            $request->user(),
        );

        return back()->with('success', 'Stock adjusted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatItem(InventoryItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'category' => $item->category->value,
            'category_label' => $item->category->label(),
            'unit' => $item->unit->value,
            'unit_label' => $item->unit->label(),
            'current_stock' => (float) $item->current_stock,
            'reorder_level' => (float) $item->reorder_level,
            'is_low_stock' => $item->isLowStock(),
            'location_type' => $item->location_type,
            'location_id' => $item->location_id,
        ];
    }
}
