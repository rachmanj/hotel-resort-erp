<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\HousekeepingEfficiencyReport;
use App\Support\CsvExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HousekeepingEfficiencyController extends Controller
{
    public function __construct(
        private HousekeepingEfficiencyReport $report,
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

        return Inertia::render('Reports/HkEfficiency', [
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
        $rows = $data['by_housekeeper']->map(fn (array $row): array => [
            $row['housekeeper_name'],
            $row['rooms_assigned'],
            $row['rooms_completed'],
            $row['avg_clean_minutes'] ?? '',
        ])->all();

        return CsvExporter::stream(
            "hk-efficiency-{$startDate->toDateString()}-{$endDate->toDateString()}.csv",
            ['Housekeeper', 'Rooms Assigned', 'Rooms Completed', 'Avg Clean Minutes'],
            $rows,
        );
    }
}
