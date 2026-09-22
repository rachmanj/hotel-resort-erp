<?php

namespace App\Services\Reports;

use App\Enums\FolioItemType;
use App\Models\FolioItem;
use Carbon\Carbon;

/**
 * PBJT output-tax recap for room and F&B revenue.
 *
 * Those prices are sold tax inclusive, so the tax is not a separate folio line and
 * never reaches tax_transactions. The recap carves the split back out of the posted
 * folio items: gross is what the guest paid, dpp + service charge + tax add up to it.
 */
class PbjtReport
{
    /**
     * @return array{
     *     period: string,
     *     by_item_type: list<array{item_type: string, label: string, gross: float, dpp: float, service_charge: float, tax: float, count: int}>,
     *     totals: array{gross: float, dpp: float, service_charge: float, tax: float, count: int}
     * }
     */
    public function generate(int $hotelId, string $period): array
    {
        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $items = FolioItem::query()
            ->join('folios', 'folio_items.folio_id', '=', 'folios.id')
            ->where('folios.hotel_id', $hotelId)
            ->where('folio_items.is_tax_inclusive', true)
            ->whereBetween('folio_items.posted_at', [$start, $end])
            ->select([
                'folio_items.item_type',
                'folio_items.amount',
                'folio_items.tax_amount',
                'folio_items.service_charge_amount',
                'folio_items.is_tax_inclusive',
            ])
            ->get();

        $rows = [];

        foreach ([FolioItemType::Room, FolioItemType::Fb] as $type) {
            $typeItems = $items->where('item_type', $type);

            if ($typeItems->isEmpty()) {
                continue;
            }

            $rows[] = [
                'item_type' => $type->value,
                'label' => $type->label(),
                'gross' => round($typeItems->sum(fn (FolioItem $item) => $item->line_total), 2),
                'dpp' => round($typeItems->sum(fn (FolioItem $item) => $item->dpp_amount), 2),
                'service_charge' => round($typeItems->sum(fn (FolioItem $item) => (float) $item->service_charge_amount), 2),
                'tax' => round($typeItems->sum(fn (FolioItem $item) => (float) $item->tax_amount), 2),
                'count' => $typeItems->count(),
            ];
        }

        return [
            'period' => $period,
            'by_item_type' => $rows,
            'totals' => [
                'gross' => round(array_sum(array_column($rows, 'gross')), 2),
                'dpp' => round(array_sum(array_column($rows, 'dpp')), 2),
                'service_charge' => round(array_sum(array_column($rows, 'service_charge')), 2),
                'tax' => round(array_sum(array_column($rows, 'tax')), 2),
                'count' => (int) array_sum(array_column($rows, 'count')),
            ],
        ];
    }
}
