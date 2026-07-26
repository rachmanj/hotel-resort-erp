<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\ReservationStatus;
use App\Http\Requests\StoreOrderRequest;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->with(['restaurantTable', 'openedBy:id,name', 'reservation.guest'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('order_type'), fn ($q) => $q->where('order_type', $request->string('order_type')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => $this->formatOrder($order));

        return Inertia::render('FB/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'order_type']),
            'statusOptions' => collect(OrderStatus::cases())->map(fn (OrderStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'typeOptions' => collect(OrderType::cases())->map(fn (OrderType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('FB/Orders/Create', [
            'menuCategories' => MenuCategory::query()
                ->with(['items' => fn ($q) => $q->where('is_available', true)->orderBy('name')])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (MenuCategory $cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'items' => $cat->items->map(fn ($item) => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'price' => (float) $item->price,
                        'description' => $item->description,
                    ]),
                ]),
            'tables' => RestaurantTable::query()->orderBy('name')->get(['id', 'name', 'area', 'status']),
            'checkedInReservations' => Reservation::query()
                ->with('guest:id,full_name')
                ->where('status', ReservationStatus::CheckedIn->value)
                ->orderByDesc('arrival_date')
                ->get()
                ->map(fn (Reservation $r) => [
                    'id' => $r->id,
                    'reservation_code' => $r->reservation_code,
                    'guest_name' => $r->guest?->full_name,
                ]),
            'orderTypes' => collect(OrderType::cases())->map(fn (OrderType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function store(StoreOrderRequest $request): RedirectResponse
    {
        $order = $this->orderService->createOrder(
            orderType: $request->string('order_type')->toString(),
            items: $request->validated('items'),
            openedBy: $request->user(),
            restaurantTableId: $request->integer('restaurant_table_id') ?: null,
            reservationId: $request->integer('reservation_id') ?: null,
            chargedToRoom: $request->boolean('charged_to_room'),
        );

        return redirect()
            ->route('fb.orders.show', $order)
            ->with('success', 'Order created successfully.');
    }

    public function show(Order $order): Response
    {
        $order->load(['items.menuItem', 'restaurantTable', 'openedBy:id,name', 'reservation.guest', 'folioItem']);

        return Inertia::render('FB/Orders/Show', [
            'order' => $this->formatOrderDetail($order),
            'statusOptions' => collect(OrderStatus::cases())
                ->reject(fn (OrderStatus $s) => in_array($s, [OrderStatus::Cancelled, OrderStatus::Served], true))
                ->map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function cancel(Order $order): RedirectResponse
    {
        $this->orderService->cancelOrder($order);

        return back()->with('success', 'Order cancelled.');
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate(['status' => 'required|in:new,preparing,ready,served']);

        $this->orderService->updateStatus($order, $request->string('status')->toString());

        return back()->with('success', 'Order status updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'order_type' => $order->order_type->value,
            'order_type_label' => $order->order_type->label(),
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'total_amount' => (float) $order->total_amount,
            'charged_to_room' => $order->charged_to_room,
            'table' => $order->restaurantTable?->name,
            'guest' => $order->reservation?->guest?->full_name,
            'opened_by' => $order->openedBy?->name,
            'created_at' => $order->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatOrderDetail(Order $order): array
    {
        return array_merge($this->formatOrder($order), [
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->menuItem?->name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => $item->lineTotal(),
                'notes' => $item->notes,
                'status' => $item->status->value,
                'status_label' => $item->status->label(),
            ]),
            'folio_item_id' => $order->folio_item_id,
        ]);
    }
}
