<?php

namespace App\Services\Reports;

use App\Enums\HousekeepingShift;
use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FbSalesReport
{
    /**
     * @return array{
     *     by_category: Collection<int, array{category_id: int, category_name: string, quantity: int, amount: float}>,
     *     by_item: Collection<int, array{item_id: int, item_name: string, category_name: string, quantity: int, amount: float}>,
     *     by_shift: Collection<int, array{shift: string, label: string, quantity: int, amount: float}>,
     *     totals: array{quantity: int, amount: float}
     * }
     */
    public function generate(int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        $baseQuery = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->join('menu_categories', 'menu_items.menu_category_id', '=', 'menu_categories.id')
            ->where('orders.hotel_id', $hotelId)
            ->where('orders.status', OrderStatus::Served->value)
            ->whereBetween('orders.created_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ]);

        $byCategory = (clone $baseQuery)
            ->groupBy('menu_categories.id', 'menu_categories.name')
            ->select([
                'menu_categories.id as category_id',
                'menu_categories.name as category_name',
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(order_items.quantity * order_items.unit_price) as amount'),
            ])
            ->orderBy('menu_categories.name')
            ->get()
            ->map(fn ($row): array => [
                'category_id' => (int) $row->category_id,
                'category_name' => $row->category_name,
                'quantity' => (int) $row->quantity,
                'amount' => round((float) $row->amount, 2),
            ]);

        $byItem = (clone $baseQuery)
            ->groupBy('menu_items.id', 'menu_items.name', 'menu_categories.name')
            ->select([
                'menu_items.id as item_id',
                'menu_items.name as item_name',
                'menu_categories.name as category_name',
                DB::raw('SUM(order_items.quantity) as quantity'),
                DB::raw('SUM(order_items.quantity * order_items.unit_price) as amount'),
            ])
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row): array => [
                'item_id' => (int) $row->item_id,
                'item_name' => $row->item_name,
                'category_name' => $row->category_name,
                'quantity' => (int) $row->quantity,
                'amount' => round((float) $row->amount, 2),
            ]);

        $orderItems = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.hotel_id', $hotelId)
            ->where('orders.status', OrderStatus::Served->value)
            ->whereBetween('orders.created_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->select([
                'order_items.quantity',
                'order_items.unit_price',
                'orders.created_at',
            ])
            ->get();

        $byShift = collect(HousekeepingShift::cases())->map(function (HousekeepingShift $shift) use ($orderItems): array {
            $shiftItems = $orderItems->filter(fn ($item) => $this->resolveShift(Carbon::parse($item->created_at)) === $shift);

            return [
                'shift' => $shift->value,
                'label' => $shift->label(),
                'quantity' => (int) $shiftItems->sum('quantity'),
                'amount' => round($shiftItems->sum(fn ($item) => (int) $item->quantity * (float) $item->unit_price), 2),
            ];
        })->filter(fn (array $row): bool => $row['quantity'] > 0)->values();

        $totalQuantity = (int) $byItem->sum('quantity');
        $totalAmount = round((float) $byItem->sum('amount'), 2);

        return [
            'by_category' => $byCategory,
            'by_item' => $byItem,
            'by_shift' => $byShift,
            'totals' => [
                'quantity' => $totalQuantity,
                'amount' => $totalAmount,
            ],
        ];
    }

    private function resolveShift(Carbon $timestamp): HousekeepingShift
    {
        $hour = (int) $timestamp->format('G');

        if ($hour >= 6 && $hour < 14) {
            return HousekeepingShift::Morning;
        }

        if ($hour >= 14 && $hour < 22) {
            return HousekeepingShift::Afternoon;
        }

        return HousekeepingShift::Night;
    }
}
