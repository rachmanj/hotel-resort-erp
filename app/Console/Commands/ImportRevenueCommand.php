<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Models\RevenueCategory;
use App\Models\RevenueImport;
use App\Models\RevenueImportLine;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ImportRevenueCommand extends Command
{
    protected $signature = 'import:revenue
                            {--file= : path to normalized CSV}
                            {--period= : YYYY-MM}
                            {--hotel-id= : optional hotel id; default = first hotel}';

    protected $description = 'Import historical revenue data from a normalized CSV file';

    public function handle(): int
    {
        $filePath = $this->option('file');
        $period = $this->option('period');

        if (! is_string($filePath) || $filePath === '' || ! is_readable($filePath)) {
            $this->error('A readable --file path is required.');

            return self::FAILURE;
        }

        if (! is_string($period) || $period === '' || ! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('A valid --period in YYYY-MM format is required.');

            return self::FAILURE;
        }

        $hotelId = $this->resolveHotelId();

        if ($hotelId === null) {
            $this->error('No hotel found. Provide --hotel-id or seed a hotel first.');

            return self::FAILURE;
        }

        $categoryMap = RevenueCategory::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotelId)
            ->pluck('id', 'code');

        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            $this->error("Unable to open file: {$filePath}");

            return self::FAILURE;
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            $this->error('CSV file is empty.');

            return self::FAILURE;
        }

        $parsedRows = [];
        $skipped = 0;
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;

            if ($this->isBlankRow($row)) {
                continue;
            }

            $parsed = $this->parseRow($row, $categoryMap, $lineNumber);

            if ($parsed === null) {
                $skipped++;

                continue;
            }

            $parsedRows[] = $parsed;
        }

        fclose($handle);

        if ($parsedRows === []) {
            $this->error('No valid rows found in CSV.');

            if ($skipped > 0) {
                $this->warn("Skipped {$skipped} malformed row(s).");
            }

            return self::FAILURE;
        }

        $grossTotal = round(array_sum(array_column($parsedRows, 'amount')), 2);

        DB::transaction(function () use ($hotelId, $period, $filePath, $grossTotal, $parsedRows): void {
            $import = RevenueImport::query()
                ->withoutGlobalScope('hotel')
                ->where('hotel_id', $hotelId)
                ->where('period', $period)
                ->first();

            if ($import !== null) {
                RevenueImportLine::query()
                    ->withoutGlobalScope('hotel')
                    ->where('revenue_import_id', $import->id)
                    ->delete();

                $import->update([
                    'source_file' => basename($filePath),
                    'gross_total' => $grossTotal,
                    'net_total' => $grossTotal,
                    'status' => 'imported',
                    'imported_by' => null,
                    'imported_at' => now(),
                ]);
            } else {
                $import = RevenueImport::query()->create([
                    'hotel_id' => $hotelId,
                    'period' => $period,
                    'source_file' => basename($filePath),
                    'gross_total' => $grossTotal,
                    'net_total' => $grossTotal,
                    'status' => 'imported',
                    'imported_by' => null,
                    'imported_at' => now(),
                ]);
            }

            foreach ($parsedRows as $row) {
                RevenueImportLine::query()->create([
                    'revenue_import_id' => $import->id,
                    'hotel_id' => $hotelId,
                    'transaction_date' => $row['transaction_date'],
                    'invoice_no' => $row['invoice_no'],
                    'guest_name' => $row['guest_name'],
                    'revenue_category_id' => $row['revenue_category_id'],
                    'category_code' => $row['category_code'],
                    'amount' => $row['amount'],
                ]);
            }
        });

        $lineCount = count($parsedRows);
        $formattedTotal = number_format($grossTotal, 0, ',', '.');

        $this->info("Imported {$lineCount} lines for period {$period} (hotel {$hotelId}), gross_total = Rp {$formattedTotal}");

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} malformed row(s).");
        }

        return self::SUCCESS;
    }

    private function resolveHotelId(): ?int
    {
        $hotelIdOption = $this->option('hotel-id');

        if (is_string($hotelIdOption) && $hotelIdOption !== '') {
            $hotelId = (int) $hotelIdOption;

            return Hotel::query()->whereKey($hotelId)->exists() ? $hotelId : null;
        }

        return Hotel::query()->orderBy('id')->value('id');
    }

    /**
     * @param  array<int, string|null>|false  $row
     */
    private function isBlankRow(array|false $row): bool
    {
        if ($row === false) {
            return true;
        }

        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  Collection<string, int>  $categoryMap
     * @return array{
     *     transaction_date: string,
     *     invoice_no: ?string,
     *     guest_name: ?string,
     *     revenue_category_id: ?int,
     *     category_code: ?string,
     *     amount: float
     * }|null
     */
    private function parseRow(array $row, $categoryMap, int $lineNumber): ?array
    {
        if (count($row) < 5) {
            $this->warn("Line {$lineNumber}: expected 5 columns, skipping.");

            return null;
        }

        [$date, $invoiceNo, $guestName, $categoryCode, $amountRaw] = $row;

        try {
            $transactionDate = Carbon::parse((string) $date)->toDateString();
        } catch (\Throwable) {
            $this->warn("Line {$lineNumber}: invalid date \"{$date}\", skipping.");

            return null;
        }

        $categoryCode = trim((string) $categoryCode);
        $revenueCategoryId = $categoryCode !== '' ? $categoryMap->get($categoryCode) : null;

        if ($categoryCode !== '' && $revenueCategoryId === null) {
            $this->warn("Line {$lineNumber}: unknown category_code \"{$categoryCode}\", skipping.");

            return null;
        }

        $amount = $this->parseAmount((string) $amountRaw);

        if ($amount === null) {
            $this->warn("Line {$lineNumber}: invalid amount \"{$amountRaw}\", skipping.");

            return null;
        }

        return [
            'transaction_date' => $transactionDate,
            'invoice_no' => $this->nullableString($invoiceNo),
            'guest_name' => $this->nullableString($guestName),
            'revenue_category_id' => $revenueCategoryId !== null ? (int) $revenueCategoryId : null,
            'category_code' => $categoryCode !== '' ? $categoryCode : null,
            'amount' => $amount,
        ];
    }

    private function parseAmount(string $raw): ?float
    {
        $normalized = str_replace([',', ' '], '', trim($raw));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
