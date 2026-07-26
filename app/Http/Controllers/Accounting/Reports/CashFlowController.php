<?php

namespace App\Http\Controllers\Accounting\Reports;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Services\Accounting\GlPostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashFlowController extends Controller
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function index(Request $request): Response
    {
        $hotelId = (int) session('current_hotel_id');
        $startDate = $request->filled('from') ? Carbon::parse($request->string('from')) : now()->startOfMonth();
        $endDate = $request->filled('to') ? Carbon::parse($request->string('to')) : now()->endOfMonth();

        $cashAccounts = ChartOfAccount::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('account_code', 'like', '1-11%')
                    ->orWhere('account_code', 'like', '1-12%');
            })
            ->orderBy('account_code')
            ->get();

        $openingCash = round($cashAccounts->sum(function (ChartOfAccount $account) use ($startDate, $hotelId): float {
            return $this->glPostingService->getBalance(
                $account,
                $startDate->copy()->subDay(),
                $hotelId,
            );
        }), 2);

        $closingCash = round($cashAccounts->sum(function (ChartOfAccount $account) use ($endDate, $hotelId): float {
            return $this->glPostingService->getBalance($account, $endDate, $hotelId);
        }), 2);

        $netIncome = $this->glPostingService->getIncomeStatement($hotelId, $startDate, $endDate)['net_income'];
        $netCashChange = round($closingCash - $openingCash, 2);

        return Inertia::render('Accounting/Reports/CashFlow', [
            'filters' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
            'summary' => [
                'opening_cash' => $openingCash,
                'closing_cash' => $closingCash,
                'net_income' => $netIncome,
                'net_cash_change' => $netCashChange,
                'investing_activities' => 0.0,
                'financing_activities' => 0.0,
            ],
        ]);
    }
}
