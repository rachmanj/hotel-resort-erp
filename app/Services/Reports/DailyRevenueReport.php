<?php

namespace App\Services\Reports;

use App\Enums\FolioItemType;
use App\Enums\PaymentMethod;
use App\Models\FolioItem;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DailyRevenueReport
{
    /**
     * @return array{
     *     by_department: Collection<int, array{department: string, label: string, amount: float}>,
     *     by_payment_method: Collection<int, array{method: string, label: string, amount: float}>,
     *     by_date: Collection<int, array{date: string, room: float, fb: float, spa: float, misc: float, total: float}>,
     *     totals: array{revenue: float, payments: float}
     * }
     */
    public function generate(int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        $departmentTypes = [
            FolioItemType::Room,
            FolioItemType::Fb,
            FolioItemType::Spa,
            FolioItemType::Misc,
        ];

        $items = FolioItem::query()
            ->join('folios', 'folio_items.folio_id', '=', 'folios.id')
            ->where('folios.hotel_id', $hotelId)
            ->whereBetween('folio_items.posted_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->whereIn('folio_items.item_type', collect($departmentTypes)->map->value->all())
            ->select([
                'folio_items.item_type',
                'folio_items.amount',
                'folio_items.tax_amount',
                'folio_items.service_charge_amount',
                'folio_items.posted_at',
            ])
            ->get();

        $byDepartment = collect($departmentTypes)->map(function (FolioItemType $type) use ($items): array {
            $typeItems = $items->where('item_type', $type->value);

            return [
                'department' => $type->value,
                'label' => $type->label(),
                'amount' => round($typeItems->sum(fn ($item) => $this->lineTotal($item)), 2),
            ];
        });

        $payments = Payment::query()
            ->join('folios', 'payments.folio_id', '=', 'folios.id')
            ->where('folios.hotel_id', $hotelId)
            ->where('payments.is_refund', false)
            ->whereBetween('payments.paid_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->select(['payments.method', 'payments.amount'])
            ->get();

        $byPaymentMethod = collect(PaymentMethod::cases())->map(function (PaymentMethod $method) use ($payments): array {
            return [
                'method' => $method->value,
                'label' => $method->label(),
                'amount' => round((float) $payments->where('method', $method->value)->sum('amount'), 2),
            ];
        })->filter(fn (array $row): bool => $row['amount'] > 0)->values();

        $byDate = collect();
        $current = $startDate->copy()->startOfDay();

        while ($current <= $endDate->copy()->endOfDay()) {
            $date = $current->toDateString();
            $dayItems = $items->filter(fn ($item) => Carbon::parse($item->posted_at)->toDateString() === $date);

            $room = round($dayItems->where('item_type', FolioItemType::Room->value)->sum(fn ($item) => $this->lineTotal($item)), 2);
            $fb = round($dayItems->where('item_type', FolioItemType::Fb->value)->sum(fn ($item) => $this->lineTotal($item)), 2);
            $spa = round($dayItems->where('item_type', FolioItemType::Spa->value)->sum(fn ($item) => $this->lineTotal($item)), 2);
            $misc = round($dayItems->where('item_type', FolioItemType::Misc->value)->sum(fn ($item) => $this->lineTotal($item)), 2);

            $byDate->push([
                'date' => $date,
                'room' => $room,
                'fb' => $fb,
                'spa' => $spa,
                'misc' => $misc,
                'total' => round($room + $fb + $spa + $misc, 2),
            ]);

            $current->addDay();
        }

        $totalRevenue = round($byDepartment->sum('amount'), 2);
        $totalPayments = round((float) $payments->sum('amount'), 2);

        return [
            'by_department' => $byDepartment,
            'by_payment_method' => $byPaymentMethod,
            'by_date' => $byDate,
            'totals' => [
                'revenue' => $totalRevenue,
                'payments' => $totalPayments,
            ],
        ];
    }

    private function lineTotal(object $item): float
    {
        return (float) $item->amount + (float) $item->tax_amount + (float) $item->service_charge_amount;
    }
}
