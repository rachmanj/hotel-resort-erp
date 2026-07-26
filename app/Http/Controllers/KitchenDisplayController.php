<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KitchenDisplayController extends Controller
{
    public function __construct(
        private OrderService $orderService,
    ) {}

    public function index(Request $request): Response
    {
        $orders = $this->orderService->getActiveKitchenOrders();

        $columns = collect([
            OrderStatus::New,
            OrderStatus::Preparing,
            OrderStatus::Ready,
            OrderStatus::Served,
        ])->map(fn (OrderStatus $status) => [
            'key' => $status->value,
            'label' => $status->label(),
            'color' => $status->color(),
            'orders' => $orders->where('status', $status)->values()->map(fn (Order $order) => [
                'id' => $order->id,
                'order_no' => $order->order_no,
                'order_type' => $order->order_type->label(),
                'table' => $order->restaurantTable?->name,
                'guest' => $order->reservation?->guest?->full_name,
                'opened_by' => $order->openedBy?->name,
                'total_amount' => (float) $order->total_amount,
                'created_at' => $order->created_at?->format('H:i'),
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->menuItem?->name,
                    'quantity' => $item->quantity,
                    'notes' => $item->notes,
                    'status' => $item->status->value,
                ]),
            ]),
        ]);

        return Inertia::render('FB/KitchenDisplay', [
            'columns' => $columns,
            'hotelId' => session('current_hotel_id'),
        ]);
    }
}
