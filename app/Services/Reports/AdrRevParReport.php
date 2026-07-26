<?php

namespace App\Services\Reports;

use App\Enums\FolioItemType;
use App\Models\FolioItem;
use Carbon\Carbon;

class AdrRevParReport
{
    public function __construct(
        private OccupancyReport $occupancyReport,
    ) {}

    /**
     * @return array{
     *     current: array{room_revenue: float, rooms_sold: int, rooms_available: int, adr: float, revpar: float, occupancy_pct: float},
     *     comparison: array{room_revenue: float, rooms_sold: int, rooms_available: int, adr: float, revpar: float, occupancy_pct: float},
     *     variance: array{adr_pct: float|null, revpar_pct: float|null, occupancy_pct: float|null}
     * }
     */
    public function generate(int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        $current = $this->periodMetrics($hotelId, $startDate, $endDate);

        $days = (int) $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1;
        $compareEnd = $startDate->copy()->subDay()->endOfDay();
        $compareStart = $compareEnd->copy()->subDays($days - 1)->startOfDay();

        $comparison = $this->periodMetrics($hotelId, $compareStart, $compareEnd);

        return [
            'current' => $current,
            'comparison' => $comparison,
            'variance' => [
                'adr_pct' => $this->percentChange($comparison['adr'], $current['adr']),
                'revpar_pct' => $this->percentChange($comparison['revpar'], $current['revpar']),
                'occupancy_pct' => $this->percentChange($comparison['occupancy_pct'], $current['occupancy_pct']),
            ],
        ];
    }

    /**
     * @return array{room_revenue: float, rooms_sold: int, rooms_available: int, adr: float, revpar: float, occupancy_pct: float}
     */
    private function periodMetrics(int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        $roomRevenue = (float) FolioItem::query()
            ->join('folios', 'folio_items.folio_id', '=', 'folios.id')
            ->where('folios.hotel_id', $hotelId)
            ->where('folio_items.item_type', FolioItemType::Room->value)
            ->whereBetween('folio_items.posted_at', [
                $startDate->copy()->startOfDay(),
                $endDate->copy()->endOfDay(),
            ])
            ->selectRaw('SUM(folio_items.amount + folio_items.tax_amount + folio_items.service_charge_amount) as total')
            ->value('total');

        $roomRevenue = round($roomRevenue, 2);

        $occupancy = $this->occupancyReport->generate($hotelId, $startDate, $endDate);
        $roomsSold = $occupancy['summary']['rooms_sold'];
        $roomsAvailable = $occupancy['summary']['rooms_available'];
        $occupancyPct = $occupancy['summary']['occupancy_pct'];

        $adr = $roomsSold > 0 ? round($roomRevenue / $roomsSold, 2) : 0.0;
        $revpar = $roomsAvailable > 0 ? round($roomRevenue / $roomsAvailable, 2) : 0.0;

        return [
            'room_revenue' => $roomRevenue,
            'rooms_sold' => $roomsSold,
            'rooms_available' => $roomsAvailable,
            'adr' => $adr,
            'revpar' => $revpar,
            'occupancy_pct' => $occupancyPct,
        ];
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous === 0.0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}
