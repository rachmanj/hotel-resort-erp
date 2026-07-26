<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\DailyRevenueReport;
use App\Support\CsvExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyRevenueController extends Controller
{
    public function __construct(
        private DailyRevenueReport $report,
    ) {}

    public function index(Request $request): Response|StreamedResponse
    {
        $hotelId = (int) session('current_hotel_id');
        $startDate = $request->filled('from') ? Carbon::parse($request->string('from')) : now()->startOfMonth();
        $endDate = $request->filled('to') ? Carbon::parse($request->string('to')) : now()->endOfMonth();

        $data = $this->report->generate($hotelId, $startDate, $endDate);

        if ($request->string('export')->toString() === 'csv') {
            return $this->exportCsv($data, $startDate, $endDate);
        }

        return Inertia::render('Reports/DailyRevenue', [
            'report' => $data,
            'filters' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function exportCsv(array $data, Carbon $startDate, Carbon $endDate): StreamedResponse
    {
        $rows = [];

        foreach ($data['by_date'] as $row) {
            $rows[] = [
                $row['date'],
                $row['room'],
                $row['fb'],
                $row['spa'],
                $row['misc'],
                $row['total'],
            ];
        }

        return CsvExporter::stream(
            "daily-revenue-{$startDate->toDateString()}-{$endDate->toDateString()}.csv",
            ['Date', 'Room', 'F&B', 'Spa', 'Misc', 'Total'],
            $rows,
        );
    }
}
