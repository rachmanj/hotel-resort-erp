<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\RevenueReport;
use App\Support\CsvExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevenueController extends Controller
{
    public function __construct(
        private RevenueReport $report,
    ) {}

    public function index(Request $request): Response|StreamedResponse
    {
        $hotelId = (int) session('current_hotel_id');
        $month = $request->filled('month') ? $request->string('month')->toString() : now()->format('Y-m');
        $startDate = Carbon::parse($month.'-01')->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $data = $this->report->generate($hotelId, $startDate, $endDate);

        if ($request->string('export')->toString() === 'csv') {
            return $this->exportCsv($data, $month);
        }

        return Inertia::render('Reports/Revenue', [
            'report' => $data,
            'filters' => [
                'month' => $month,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function exportCsv(array $data, string $month): StreamedResponse
    {
        $categories = collect($data['categories']);
        $headers = array_merge(
            ['Date'],
            $categories->pluck('name')->all(),
            ['Total'],
        );

        $rows = [];

        foreach ($data['by_date'] as $row) {
            $csvRow = [$row['date']];

            foreach ($categories as $category) {
                $csvRow[] = $row[$category['code']] ?? 0;
            }

            $csvRow[] = $row['total'];
            $rows[] = $csvRow;
        }

        return CsvExporter::stream(
            "revenue-{$month}.csv",
            $headers,
            $rows,
        );
    }
}
