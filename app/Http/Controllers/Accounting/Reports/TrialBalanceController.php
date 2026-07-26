<?php

namespace App\Http\Controllers\Accounting\Reports;

use App\Http\Controllers\Controller;
use App\Services\Accounting\GlPostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrialBalanceController extends Controller
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function index(Request $request): Response
    {
        $hotelId = (int) session('current_hotel_id');
        $asOfDate = $request->filled('as_of') ? Carbon::parse($request->string('as_of')) : now();

        $rows = $this->glPostingService->getTrialBalance($hotelId, $asOfDate);
        $totalDebit = round($rows->sum('debit'), 2);
        $totalCredit = round($rows->sum('credit'), 2);

        return Inertia::render('Accounting/Reports/TrialBalance', [
            'rows' => $rows,
            'filters' => ['as_of' => $asOfDate->toDateString()],
            'totals' => [
                'debit' => $totalDebit,
                'credit' => $totalCredit,
            ],
        ]);
    }
}
