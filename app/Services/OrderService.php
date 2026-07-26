<?php

namespace App\Services;

use App\Enums\FolioItemType;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\RestaurantTableStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    public function __construct(
        private FolioPostingService $folioPostingService,
        private KdsBroadcastService $kdsBroadcastService,
    ) {}

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int, notes?: string|null}>  $items
     */
    public function createOrder(
        string $orderType,
        array $items,
        User $openedBy,
        ?int $restaurantTableId = null,
        ?int $reservationId = null,
        bool $chargedToRoom = false,
    ): Order {
        if ($items === []) {
            throw new InvalidArgumentException('Order must have at least one item.');
        }

        return DB::transaction(function () use ($orderType, $items, $openedBy, $restaurantTableId, $reservationId, $chargedToRoom): Order {
            $order = Order::query()->create([
                'order_no' => $this->generateOrderNumber(),
                'order_type' => $orderType,
                'restaurant_table_id' => $restaurantTableId,
                'reservation_id' => $reservationId,
                'status' => OrderStatus::New->value,
                'opened_by' => $openedBy->id,
                'total_amount' => 0,
                'charged_to_room' => $chargedToRoom,
            ]);

            $this->addItems($order, $items);

            if ($restaurantTableId !== null) {
                $order->restaurantTable?->update(['status' => RestaurantTableStatus::Occupied->value]);
            }

            if ($chargedToRoom && $reservationId !== null) {
                $this->chargeToRoom($order, $reservationId, $openedBy);
            }

            $this->kdsBroadcastService->broadcastOrderUpdate($order->fresh(['items.menuItem', 'restaurantTable', 'openedBy']));

            return $order;
        });
    }

    /**
     * @param  array<int, array{menu_item_id: int, quantity: int, notes?: string|null}>  $items
     */
    public function addItems(Order $order, array $items): void
    {
        if ($order->status === OrderStatus::Cancelled || $order->status === OrderStatus::Served) {
            throw new InvalidArgumentException('Cannot add items to a closed order.');
        }

        foreach ($items as $item) {
            $menuItem = MenuItem::query()->findOrFail($item['menu_item_id']);

            if (! $menuItem->is_available) {
                throw new InvalidArgumentException("Menu item {$menuItem->name} is not available.");
            }

            OrderItem::query()->create([
                'order_id' => $order->id,
                'menu_item_id' => $menuItem->id,
                'quantity' => $item['quantity'],
                'unit_price' => $menuItem->price,
                'notes' => $item['notes'] ?? null,
                'status' => OrderItemStatus::New->value,
            ]);
        }

        $order->update(['total_amount' => $this->calculateTotal($order)]);
        $this->kdsBroadcastService->broadcastOrderUpdate($order->fresh(['items.menuItem', 'restaurantTable', 'openedBy']));
    }

    public function updateStatus(Order $order, string $status): Order
    {
        $newStatus = OrderStatus::from($status);

        if ($order->status === OrderStatus::Cancelled) {
            throw new InvalidArgumentException('Cannot update a cancelled order.');
        }

        $order->update(['status' => $newStatus->value]);

        if ($newStatus === OrderStatus::Served && $order->restaurant_table_id !== null) {
            $order->restaurantTable?->update(['status' => RestaurantTableStatus::Available->value]);
        }

        $this->kdsBroadcastService->broadcastOrderUpdate($order->fresh(['items.menuItem', 'restaurantTable', 'openedBy']));

        return $order;
    }

    public function updateItemStatus(OrderItem $orderItem, string $status): OrderItem
    {
        $orderItem->update(['status' => OrderItemStatus::from($status)->value]);

        $order = $orderItem->order;
        $this->syncOrderStatusFromItems($order);
        $this->kdsBroadcastService->broadcastOrderUpdate($order->fresh(['items.menuItem', 'restaurantTable', 'openedBy']));

        return $orderItem;
    }

    public function cancelOrder(Order $order): Order
    {
        if ($order->status === OrderStatus::Served) {
            throw new InvalidArgumentException('Cannot cancel a served order.');
        }

        $order->update(['status' => OrderStatus::Cancelled->value]);

        if ($order->restaurant_table_id !== null) {
            $order->restaurantTable?->update(['status' => RestaurantTableStatus::Available->value]);
        }

        $this->kdsBroadcastService->broadcastOrderUpdate($order->fresh(['items.menuItem', 'restaurantTable', 'openedBy']));

        return $order;
    }

    public function calculateTotal(Order $order): float
    {
        return round(
            (float) $order->items()->selectRaw('SUM(quantity * unit_price) as total')->value('total') ?? 0,
            2,
        );
    }

    /**
     * @return Collection<int, Order>
     */
    public function getActiveKitchenOrders(): Collection
    {
        return Order::query()
            ->with(['items.menuItem', 'restaurantTable', 'openedBy:id,name', 'reservation.guest'])
            ->whereIn('status', [
                OrderStatus::New->value,
                OrderStatus::Preparing->value,
                OrderStatus::Ready->value,
            ])
            ->orderBy('created_at')
            ->get();
    }

    private function chargeToRoom(Order $order, int $reservationId, User $postedBy): void
    {
        $reservation = Reservation::query()->with('guest')->findOrFail($reservationId);

        $folio = $this->folioPostingService->findOrCreateMasterFolio(
            $reservation->hotel_id,
            $reservation->id,
            $reservation->guest_id,
        );

        $description = 'F&B Order '.$order->order_no;
        if ($order->order_type === OrderType::RoomService) {
            $description = 'Room Service '.$order->order_no;
        }

        $folioItem = $this->folioPostingService->postCharge(
            folio: $folio,
            itemType: FolioItemType::Fb->value,
            description: $description,
            amount: (float) $order->total_amount,
            referenceType: Order::class,
            referenceId: $order->id,
            postedBy: $postedBy,
        );

        $order->update(['folio_item_id' => $folioItem->id]);
    }

    private function syncOrderStatusFromItems(Order $order): void
    {
        $order->load('items');
        $statuses = $order->items->pluck('status')->map(fn ($s) => $s->value);

        if ($statuses->every(fn ($s) => $s === OrderItemStatus::Served->value)) {
            $order->update(['status' => OrderStatus::Served->value]);
        } elseif ($statuses->contains(OrderItemStatus::Ready->value)) {
            $order->update(['status' => OrderStatus::Ready->value]);
        } elseif ($statuses->contains(OrderItemStatus::Preparing->value)) {
            $order->update(['status' => OrderStatus::Preparing->value]);
        }
    }

    private function generateOrderNumber(): string
    {
        return DB::transaction(function (): string {
            $datePrefix = now()->format('Ymd');
            $prefix = "ORD-{$datePrefix}-";

            $lastCode = Order::query()
                ->withoutGlobalScope('hotel')
                ->where('order_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('order_no')
                ->value('order_no');

            $sequence = 1;
            if ($lastCode !== null) {
                $sequence = (int) substr($lastCode, -4) + 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
