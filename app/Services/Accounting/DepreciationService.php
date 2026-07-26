<?php

namespace App\Services\Accounting;

use App\Enums\DepreciationMethod;
use App\Models\Asset;
use App\Models\Hotel;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DepreciationService
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    /**
     * @return Collection<int, array{asset_id: int, amount: float, posted: bool}>
     */
    public function runMonthlyBatch(Hotel $hotel, ?CarbonInterface $asOf = null): Collection
    {
        $asOfDate = Carbon::parse($asOf ?? now())->endOfMonth();
        $results = collect();

        $assets = Asset::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->whereNotNull('acquisition_cost')
            ->where('acquisition_cost', '>', 0)
            ->get()
            ->filter(fn (Asset $asset): bool => $asset->isDepreciable());

        foreach ($assets as $asset) {
            $amount = $this->calculateMonthlyDepreciation($asset, $asOfDate);

            if ($amount <= 0) {
                continue;
            }

            if ($asset->last_depreciation_date !== null
                && $asset->last_depreciation_date->format('Y-m') === $asOfDate->format('Y-m')) {
                continue;
            }

            $posted = DB::transaction(function () use ($asset, $amount, $asOfDate): bool {
                $this->glPostingService->post([
                    [
                        'hotel_id' => (int) $asset->hotel_id,
                        'chart_of_account_id' => (int) $asset->chart_of_account_id,
                        'transaction_date' => $asOfDate->toDateString(),
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => "Depreciation: {$asset->name}",
                        'reference_number' => $asset->asset_code ?? "FA-{$asset->id}",
                        'source_type' => 'asset_depreciation',
                        'source_id' => $asset->id,
                    ],
                    [
                        'hotel_id' => (int) $asset->hotel_id,
                        'chart_of_account_id' => (int) $asset->accumulated_depreciation_account_id,
                        'transaction_date' => $asOfDate->toDateString(),
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => "Depreciation: {$asset->name}",
                        'reference_number' => $asset->asset_code ?? "FA-{$asset->id}",
                        'source_type' => 'asset_depreciation',
                        'source_id' => $asset->id,
                    ],
                ]);

                $newAccumulated = round((float) $asset->accumulated_depreciation + $amount, 2);
                $netBook = round((float) $asset->acquisition_cost - $newAccumulated, 2);

                $asset->update([
                    'accumulated_depreciation' => $newAccumulated,
                    'net_book_value' => max(0, $netBook),
                    'last_depreciation_date' => $asOfDate->toDateString(),
                ]);

                return true;
            });

            $results->push([
                'asset_id' => $asset->id,
                'amount' => $amount,
                'posted' => $posted,
            ]);
        }

        return $results;
    }

    public function calculateMonthlyDepreciation(Asset $asset, CarbonInterface $asOfDate): float
    {
        if (! $asset->isDepreciable()) {
            return 0.0;
        }

        $cost = (float) $asset->acquisition_cost;
        $residual = (float) $asset->residual_value;
        $depreciableBase = max(0, $cost - $residual);
        $accumulated = (float) $asset->accumulated_depreciation;
        $remaining = max(0, $depreciableBase - $accumulated);

        if ($remaining <= 0) {
            return 0.0;
        }

        $usefulLifeMonths = (int) $asset->useful_life_years * 12;

        if ($usefulLifeMonths <= 0) {
            throw new InvalidArgumentException("Asset {$asset->id} has invalid useful life.");
        }

        $amount = match ($asset->depreciation_method) {
            DepreciationMethod::StraightLine => $depreciableBase / $usefulLifeMonths,
            DepreciationMethod::DoubleDeclining => $this->doubleDecliningMonthly($asset, $asOfDate),
        };

        return round(min($amount, $remaining), 2);
    }

    private function doubleDecliningMonthly(Asset $asset, CarbonInterface $asOfDate): float
    {
        $cost = (float) $asset->acquisition_cost;
        $residual = (float) $asset->residual_value;
        $usefulLifeMonths = (int) $asset->useful_life_years * 12;
        $rate = 2 / $usefulLifeMonths;

        $monthsElapsed = 0;
        if ($asset->acquisition_date !== null) {
            $monthsElapsed = max(0, $asset->acquisition_date->diffInMonths($asOfDate));
        }

        $bookValue = $cost;
        for ($i = 0; $i < $monthsElapsed; $i++) {
            $depreciation = $bookValue * $rate;
            $bookValue = max($residual, $bookValue - $depreciation);
        }

        return max(0, $bookValue * $rate);
    }
}
