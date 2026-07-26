<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\DepreciationMethod;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Services\Accounting\DepreciationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FixedAssetController extends Controller
{
    public function __construct(
        private DepreciationService $depreciationService,
    ) {}

    public function index(): Response
    {
        $assets = Asset::query()
            ->whereNotNull('acquisition_cost')
            ->orderBy('asset_code')
            ->orderBy('name')
            ->get()
            ->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'asset_code' => $asset->asset_code,
                'name' => $asset->name,
                'acquisition_date' => $asset->acquisition_date?->toDateString(),
                'acquisition_cost' => $asset->acquisition_cost !== null ? (float) $asset->acquisition_cost : null,
                'accumulated_depreciation' => (float) $asset->accumulated_depreciation,
                'net_book_value' => $asset->net_book_value !== null ? (float) $asset->net_book_value : null,
                'depreciation_method' => $asset->depreciation_method?->label(),
                'useful_life_years' => $asset->useful_life_years,
                'last_depreciation_date' => $asset->last_depreciation_date?->toDateString(),
                'is_depreciable' => $asset->isDepreciable(),
            ]);

        return Inertia::render('Accounting/FixedAssets/Index', [
            'assets' => $assets,
            'depreciationMethods' => collect(DepreciationMethod::cases())->map(fn (DepreciationMethod $m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
            'expenseAccounts' => ChartOfAccount::query()
                ->where('is_postable', true)
                ->where('account_code', 'like', '6-%')
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'name']),
            'accumAccounts' => ChartOfAccount::query()
                ->where('is_postable', true)
                ->where('account_code', 'like', '1-29%')
                ->orderBy('account_code')
                ->get(['id', 'account_code', 'name']),
        ]);
    }

    public function update(Request $request, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'asset_code' => ['nullable', 'string', 'max:30'],
            'acquisition_date' => ['nullable', 'date'],
            'acquisition_cost' => ['nullable', 'numeric', 'min:0'],
            'residual_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['nullable', 'integer', 'min:1'],
            'depreciation_method' => ['nullable', 'string'],
            'chart_of_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
            'accumulated_depreciation_account_id' => ['nullable', 'exists:chart_of_accounts,id'],
        ]);

        $asset->update($validated);

        if ($asset->acquisition_cost !== null) {
            $netBook = round((float) $asset->acquisition_cost - (float) $asset->accumulated_depreciation, 2);
            $asset->update(['net_book_value' => max(0, $netBook)]);
        }

        return back()->with('success', 'Fixed asset accounting data updated.');
    }

    public function runDepreciation(Request $request): RedirectResponse
    {
        $hotel = Hotel::query()->findOrFail(session('current_hotel_id'));
        $asOf = $request->filled('as_of') ? Carbon::parse($request->string('as_of')) : now();

        $results = $this->depreciationService->runMonthlyBatch($hotel, $asOf);
        $total = round($results->sum('amount'), 2);
        $count = $results->count();

        return back()->with('success', "Depreciation run complete: {$count} assets, total IDR {$total}.");
    }
}
