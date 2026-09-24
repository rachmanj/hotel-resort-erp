<?php

namespace App\Console\Commands;

use App\Enums\BoatEngineOption;
use App\Enums\DailyTripBoatClass;
use App\Enums\DiveRateItemType;
use App\Models\DivePackage;
use App\Models\DiveRateItem;
use App\Models\Hotel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportDivePriceList extends Command
{
    protected $signature = 'pratasaba:import-dive-price-list {--dry-run : Report what would change without writing to the database}';

    protected $description = 'Import 2026 dive and daily trip rate items from the Pratasaba price list CSVs';

    private const DIVE_CSV_PATH = 'database/data/price-list-2026/pratasaba-diving-packages.csv';

    private const DAILY_TRIP_CSV_PATH = 'database/data/price-list-2026/pratasaba-daily-trip-packages.csv';

    /** @var array{rate_items_created: int, rate_items_updated: int, dive_packages_created: int, dive_packages_updated: int} */
    private array $stats = [
        'rate_items_created' => 0,
        'rate_items_updated' => 0,
        'dive_packages_created' => 0,
        'dive_packages_updated' => 0,
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $hotel = Hotel::query()->orderBy('id')->first();

        if ($hotel === null) {
            $this->error('No hotel found. Seed a hotel before running this import.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        $divePath = base_path(self::DIVE_CSV_PATH);
        $dailyTripPath = base_path(self::DAILY_TRIP_CSV_PATH);

        if (! is_readable($divePath)) {
            $this->error('CSV file not found: '.self::DIVE_CSV_PATH);

            return self::FAILURE;
        }

        if (! is_readable($dailyTripPath)) {
            $this->error('CSV file not found: '.self::DAILY_TRIP_CSV_PATH);

            return self::FAILURE;
        }

        $diveRows = $this->readCsv($divePath);
        $dailyTripRows = $this->readCsv($dailyTripPath);

        if ($diveRows === null || $dailyTripRows === null) {
            return self::FAILURE;
        }

        $import = function () use ($diveRows, $dailyTripRows, $hotel, $dryRun): void {
            $this->importDiveCsv($diveRows, $hotel, $dryRun);
            $this->importDailyTripCsv($dailyTripRows, $dryRun);
        };

        if ($dryRun) {
            $import();
        } else {
            DB::transaction($import);
        }

        $this->newLine();
        $this->info('Price list import summary:');
        $this->line("  Rate items created: {$this->stats['rate_items_created']}");
        $this->line("  Rate items updated: {$this->stats['rate_items_updated']}");
        $this->line("  Dive packages created: {$this->stats['dive_packages_created']}");
        $this->line("  Dive packages updated: {$this->stats['dive_packages_updated']}");

        return self::SUCCESS;
    }

    /**
     * @param  list<array<int, string|null>>  $rows
     */
    private function importDiveCsv(array $rows, Hotel $hotel, bool $dryRun): void
    {
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $code = trim((string) ($row[0] ?? ''));
            if ($code === '') {
                continue;
            }

            $itemType = DiveRateItemType::from(trim((string) ($row[2] ?? '')));
            $boatEngine = trim((string) ($row[5] ?? ''));
            $minPax = trim((string) ($row[7] ?? ''));

            $attributes = [
                'name' => trim((string) ($row[1] ?? '')),
                'item_type' => $itemType->value,
                'route' => $this->nullableString($row[3] ?? null),
                'dive_spots' => $this->nullableString($row[4] ?? null),
                'boat_engine_option' => $boatEngine !== '' ? BoatEngineOption::from($boatEngine)->value : null,
                'boat_class' => null,
                'price' => $this->parsePrice($row[6] ?? null),
                'min_pax' => $minPax !== '' ? (int) $minPax : null,
                'valid_from' => trim((string) ($row[8] ?? '')),
                'valid_to' => trim((string) ($row[9] ?? '')),
            ];

            $this->upsertRateItem($code, $attributes, $dryRun);

            $divePackageType = $itemType->divePackageType();
            if ($divePackageType === null) {
                continue;
            }

            $packageCode = $this->divePackageCodeForRateItem($code);
            if ($packageCode === null) {
                continue;
            }

            $packageAttributes = [
                'name' => $this->divePackageNameForRateItem($code, $attributes['name']),
                'type' => $divePackageType->value,
                'price_per_person' => $attributes['price'],
                'min_pax' => $attributes['min_pax'] ?? 1,
                'is_active' => true,
            ];

            if ($dryRun) {
                $exists = DivePackage::query()
                    ->where('hotel_id', $hotel->id)
                    ->where('code', $packageCode)
                    ->exists();

                if ($exists) {
                    $this->stats['dive_packages_updated']++;
                } else {
                    $this->stats['dive_packages_created']++;
                }

                continue;
            }

            $package = DivePackage::query()->updateOrCreate(
                [
                    'hotel_id' => $hotel->id,
                    'code' => $packageCode,
                ],
                $packageAttributes,
            );

            if ($package->wasRecentlyCreated) {
                $this->stats['dive_packages_created']++;
            } else {
                $this->stats['dive_packages_updated']++;
            }
        }
    }

    /**
     * @param  list<array<int, string|null>>  $rows
     */
    private function importDailyTripCsv(array $rows, bool $dryRun): void
    {
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $code = trim((string) ($row[0] ?? ''));
            if ($code === '') {
                continue;
            }

            $itemType = DiveRateItemType::from(trim((string) ($row[2] ?? '')));
            $destination = $this->nullableString($row[3] ?? null);
            $boatClass = trim((string) ($row[4] ?? ''));

            $attributes = [
                'name' => trim((string) ($row[1] ?? '')),
                'item_type' => $itemType->value,
                'route' => $destination,
                'dive_spots' => null,
                'boat_engine_option' => null,
                'boat_class' => $boatClass !== '' ? DailyTripBoatClass::from($boatClass)->value : null,
                'price' => $this->parsePrice($row[5] ?? null),
                'min_pax' => null,
                'valid_from' => trim((string) ($row[6] ?? '')),
                'valid_to' => trim((string) ($row[7] ?? '')),
            ];

            $this->upsertRateItem($code, $attributes, $dryRun);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertRateItem(string $code, array $attributes, bool $dryRun): void
    {
        if ($dryRun) {
            $exists = DiveRateItem::query()->where('code', $code)->exists();
            if ($exists) {
                $this->stats['rate_items_updated']++;
                $this->line("  Would update rate item: {$code}");
            } else {
                $this->stats['rate_items_created']++;
                $this->line("  Would create rate item: {$code}");
            }

            return;
        }

        $item = DiveRateItem::query()->updateOrCreate(
            ['code' => $code],
            $attributes,
        );

        if ($item->wasRecentlyCreated) {
            $this->stats['rate_items_created']++;
        } else {
            $this->stats['rate_items_updated']++;
        }
    }

    private function divePackageCodeForRateItem(string $rateCode): ?string
    {
        return match ($rateCode) {
            'DV-PKG-GRP' => 'DV-PKG-GRP',
            'DV-PKG-SOLO' => 'DV-PKG-SOLO',
            'DV-NIGHT' => 'DV-NIGHT',
            'DV-DSD-GUEST-GRP' => 'DV-DSD-GUEST',
            'DV-DSD-GUEST-SOLO' => 'DV-DSD-GUEST-SOLO',
            'DV-DSD-VISITOR-GRP' => 'DV-DSD-VISITOR',
            'DV-DSD-VISITOR-SOLO' => 'DV-DSD-VISITOR-SOLO',
            default => null,
        };
    }

    private function divePackageNameForRateItem(string $rateCode, string $defaultName): string
    {
        return match ($rateCode) {
            'DV-DSD-GUEST-GRP' => 'Discover Scuba Diving (Guest Stay)',
            'DV-DSD-VISITOR-GRP' => 'Discover Scuba Diving (Visitor)',
            default => $defaultName,
        };
    }

    /**
     * @return list<array<int, string|null>>|null
     */
    private function readCsv(string $path): ?array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->error('Unable to open CSV file: '.$path);

            return null;
        }

        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function parsePrice(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }

        $normalized = str_replace([',', ' '], '', trim((string) $value));

        if ($normalized === '') {
            return 0.0;
        }

        return (float) $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) ($value ?? ''));

        return $string !== '' ? $string : null;
    }
}
