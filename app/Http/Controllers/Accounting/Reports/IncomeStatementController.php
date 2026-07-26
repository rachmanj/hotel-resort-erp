<?php

namespace App\Http\Controllers\Accounting\Reports;

use App\Http\Controllers\Controller;
use App\Services\Accounting\GlPostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IncomeStatementController extends Controller
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function index(Request $request): Response
    {
        $hotelId = (int) session('current_hotel_id');
        $startDate = $request->filled('from') ? Carbon::parse($request->string('from')) : now()->startOfMonth();
        $endDate = $request->filled('to') ? Carbon::parse($request->string('to')) : now()->endOfMonth();

        $statement = $this->glPostingService->getIncomeStatement($hotelId, $startDate, $endDate);

        return Inertia::render('Accounting/Reports/IncomeStatement', [
            'statement' => $statement,
            'filters' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
        ]);
    }
}
