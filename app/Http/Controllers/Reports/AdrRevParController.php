<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\AdrRevParReport;
use App\Support\CsvExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdrRevParController extends Controller
{
    public function __construct(
        private AdrRevParReport $report,
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

        return Inertia::render('Reports/AdrRevPar', [
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
        $rows = [
            ['Current', $data['current']['room_revenue'], $data['current']['rooms_sold'], $data['current']['adr'], $data['current']['revpar'], $data['current']['occupancy_pct']],
            ['Comparison', $data['comparison']['room_revenue'], $data['comparison']['rooms_sold'], $data['comparison']['adr'], $data['comparison']['revpar'], $data['comparison']['occupancy_pct']],
        ];

        return CsvExporter::stream(
            "adr-revpar-{$startDate->toDateString()}-{$endDate->toDateString()}.csv",
            ['Period', 'Room Revenue', 'Rooms Sold', 'ADR', 'RevPAR', 'Occupancy %'],
            $rows,
        );
    }
}
