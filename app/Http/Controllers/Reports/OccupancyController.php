<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\OccupancyReport;
use App\Support\CsvExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OccupancyController extends Controller
{
    public function __construct(
        private OccupancyReport $report,
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

        return Inertia::render('Reports/Occupancy', [
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
        $rows = $data['by_room_type']->map(fn (array $row): array => [
            $row['room_type_name'],
            $row['total_rooms'],
            $row['rooms_sold'],
            $row['rooms_available'],
            $row['occupancy_pct'],
        ])->all();

        return CsvExporter::stream(
            "occupancy-{$startDate->toDateString()}-{$endDate->toDateString()}.csv",
            ['Room Type', 'Total Rooms', 'Room Nights Sold', 'Available Room Nights', 'Occupancy %'],
            $rows,
        );
    }
}
