<?php

namespace App\Http\Controllers\Accounting\Reports;

use App\Http\Controllers\Controller;
use App\Services\Accounting\GlPostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BalanceSheetController extends Controller
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function index(Request $request): Response
    {
        $hotelId = (int) session('current_hotel_id');
        $asOfDate = $request->filled('as_of') ? Carbon::parse($request->string('as_of')) : now();

        $statement = $this->glPostingService->getBalanceSheet($hotelId, $asOfDate);

        return Inertia::render('Accounting/Reports/BalanceSheet', [
            'statement' => $statement,
            'filters' => ['as_of' => $asOfDate->toDateString()],
        ]);
    }
}
