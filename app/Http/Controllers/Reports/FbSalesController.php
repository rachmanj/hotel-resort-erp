<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\FbSalesReport;
use App\Support\CsvExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FbSalesController extends Controller
{
    public function __construct(
        private FbSalesReport $report,
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

        return Inertia::render('Reports/FbSales', [
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
        $rows = $data['by_item']->map(fn (array $row): array => [
            $row['category_name'],
            $row['item_name'],
            $row['quantity'],
            $row['amount'],
        ])->all();

        return CsvExporter::stream(
            "fb-sales-{$startDate->toDateString()}-{$endDate->toDateString()}.csv",
            ['Category', 'Item', 'Quantity', 'Amount'],
            $rows,
        );
    }
}
