<?php

namespace App\Services\Reports;

use App\Enums\FolioItemType;
use App\Models\AgentCommission;
use App\Models\FolioItem;
use App\Models\OtaFeeCharge;
use App\Models\RevenueCategory;
use App\Models\RevenueImport;
use App\Models\RevenueImportLine;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RevenueReport
{
    /**
     * @return array{
     *     data_source: 'live'|'imported',
     *     categories: Collection<int, array{code: string, name: string, sort_order: int, amount: float}>,
     *     by_date: Collection<int, array<string, float|string>>,
     *     totals: array{revenue: float, discount: float, ota_fee: float, agent_commission: float},
     *     gross_total?: float,
     *     net_total?: float
     * }
     */
    public function generate(int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        if ($this->isSingleCalendarMonth($startDate, $endDate)) {
            $period = $startDate->format('Y-m');
            $import = RevenueImport::query()
                ->withoutGlobalScope('hotel')
                ->where('hotel_id', $hotelId)
                ->where('period', $period)
                ->first();

            if ($import !== null) {
                return $this->generateFromImport($hotelId, $startDate, $endDate, $import);
            }
        }

        return $this->generateLive($hotelId, $startDate, $endDate);
    }

    /**
     * @return array{
     *     data_source: 'imported',
     *     categories: Collection<int, array{code: string, name: string, sort_order: int, amount: float}>,
     *     by_date: Collection<int, array<string, float|string>>,
     *     totals: array{revenue: float, discount: float, ota_fee: float, agent_commission: float},
     *     gross_total: float,
     *     net_total: float
     * }
     */
    private function generateFromImport(int $hotelId, Carbon $startDate, Carbon $endDate, RevenueImport $import): array
    {
        $rangeStart = $startDate->copy()->startOfDay();
        $rangeEnd = $endDate->copy()->endOfDay();

        $categories = RevenueCategory::query()
            ->where('hotel_id', $hotelId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'sort_order']);

        $lines = RevenueImportLine::query()
            ->withoutGlobalScope('hotel')
            ->where('revenue_import_id', $import->id)
            ->whereBetween('transaction_date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->get(['revenue_category_id', 'category_code', 'amount', 'transaction_date']);

        $categoryRows = $categories->map(function (RevenueCategory $category) use ($lines): array {
            $categoryLines = $lines->where('revenue_category_id', $category->id);

            return [
                'code' => $category->code,
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'amount' => round((float) $categoryLines->sum('amount'), 2),
            ];
        })->values();

        $uncategorizedLines = $lines->whereNull('revenue_category_id');

        $categoryRows->push([
            'code' => 'uncategorized',
            'name' => 'Uncategorized',
            'sort_order' => 9999,
            'amount' => round((float) $uncategorizedLines->sum('amount'), 2),
        ]);

        $byDate = collect();
        $current = $startDate->copy()->startOfDay();

        while ($current <= $rangeEnd) {
            $date = $current->toDateString();
            $dayLines = $lines->filter(
                fn ($line) => Carbon::parse($line->transaction_date)->toDateString() === $date,
            );

            $row = ['date' => $date];
            $dayTotal = 0.0;

            foreach ($categoryRows as $category) {
                if ($category['code'] === 'uncategorized') {
                    $amount = round((float) $dayLines->whereNull('revenue_category_id')->sum('amount'), 2);
                } else {
                    $categoryId = $categories->firstWhere('code', $category['code'])?->id;
                    $amount = round((float) $dayLines->where('revenue_category_id', $categoryId)->sum('amount'), 2);
                }

                $row[$category['code']] = $amount;
                $dayTotal += $amount;
            }

            $row['total'] = round($dayTotal, 2);
            $byDate->push($row);
            $current->addDay();
        }

        $grossTotal = round((float) $import->gross_total, 2);
        $netTotal = round((float) $import->net_total, 2);

        return [
            'data_source' => 'imported',
            'categories' => $categoryRows,
            'by_date' => $byDate,
            'totals' => [
                'revenue' => round($categoryRows->sum('amount'), 2),
                'discount' => 0.0,
                'ota_fee' => 0.0,
                'agent_commission' => 0.0,
            ],
            'gross_total' => $grossTotal,
            'net_total' => $netTotal,
        ];
    }

    /**
     * @return array{
     *     data_source: 'live',
     *     categories: Collection<int, array{code: string, name: string, sort_order: int, amount: float}>,
     *     by_date: Collection<int, array<string, float|string>>,
     *     totals: array{revenue: float, discount: float, ota_fee: float, agent_commission: float}
     * }
     */
    private function generateLive(int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        $rangeStart = $startDate->copy()->startOfDay();
        $rangeEnd = $endDate->copy()->endOfDay();

        $categories = RevenueCategory::query()
            ->where('hotel_id', $hotelId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'sort_order']);

        $items = FolioItem::query()
            ->join('folios', 'folio_items.folio_id', '=', 'folios.id')
            ->where('folios.hotel_id', $hotelId)
            ->whereBetween('folio_items.posted_at', [$rangeStart, $rangeEnd])
            ->select([
                'folio_items.revenue_category_id',
                'folio_items.amount',
                'folio_items.tax_amount',
                'folio_items.service_charge_amount',
                'folio_items.posted_at',
                'folio_items.item_type',
            ])
            ->get();

        $categoryRows = $categories->map(function (RevenueCategory $category) use ($items): array {
            $categoryItems = $items->where('revenue_category_id', $category->id);

            return [
                'code' => $category->code,
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'amount' => round($categoryItems->sum(fn ($item) => $this->lineTotal($item)), 2),
            ];
        })->values();

        $uncategorizedItems = $items->whereNull('revenue_category_id');

        $categoryRows->push([
            'code' => 'uncategorized',
            'name' => 'Uncategorized',
            'sort_order' => 9999,
            'amount' => round($uncategorizedItems->sum(fn ($item) => $this->lineTotal($item)), 2),
        ]);

        $byDate = collect();
        $current = $startDate->copy()->startOfDay();

        while ($current <= $rangeEnd) {
            $date = $current->toDateString();
            $dayItems = $items->filter(
                fn ($item) => Carbon::parse($item->posted_at)->toDateString() === $date,
            );

            $row = ['date' => $date];
            $dayTotal = 0.0;

            foreach ($categoryRows as $category) {
                if ($category['code'] === 'uncategorized') {
                    $amount = round(
                        $dayItems->whereNull('revenue_category_id')->sum(fn ($item) => $this->lineTotal($item)),
                        2,
                    );
                } else {
                    $categoryId = $categories->firstWhere('code', $category['code'])?->id;
                    $amount = round(
                        $dayItems->where('revenue_category_id', $categoryId)->sum(fn ($item) => $this->lineTotal($item)),
                        2,
                    );
                }

                $row[$category['code']] = $amount;
                $dayTotal += $amount;
            }

            $row['total'] = round($dayTotal, 2);
            $byDate->push($row);
            $current->addDay();
        }

        $discountTotal = round(
            abs((float) $items->where('item_type', FolioItemType::Discount->value)->sum(fn ($item) => $this->lineTotal($item))),
            2,
        );

        $otaFeeTotal = round(
            (float) OtaFeeCharge::query()
                ->where('hotel_id', $hotelId)
                ->whereBetween('earned_at', [$rangeStart, $rangeEnd])
                ->sum('fee_amount'),
            2,
        );

        $agentCommissionTotal = round(
            (float) AgentCommission::query()
                ->join('reservations', 'agent_commissions.reservation_id', '=', 'reservations.id')
                ->where('reservations.hotel_id', $hotelId)
                ->whereBetween('agent_commissions.earned_at', [$rangeStart, $rangeEnd])
                ->sum('agent_commissions.commission_amount'),
            2,
        );

        return [
            'data_source' => 'live',
            'categories' => $categoryRows,
            'by_date' => $byDate,
            'totals' => [
                'revenue' => round($categoryRows->sum('amount'), 2),
                'discount' => $discountTotal,
                'ota_fee' => $otaFeeTotal,
                'agent_commission' => $agentCommissionTotal,
            ],
        ];
    }

    private function isSingleCalendarMonth(Carbon $startDate, Carbon $endDate): bool
    {
        return $startDate->format('Y-m') === $endDate->format('Y-m');
    }

    private function lineTotal(object $item): float
    {
        return (float) $item->amount + (float) $item->tax_amount + (float) $item->service_charge_amount;
    }
}
