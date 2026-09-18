<?php

namespace App\Console\Commands;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\DepreciationMethod;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportPriceList extends Command
{
    protected $signature = 'pratasaba:import-price-list {--dry-run : Report what would change without writing to the database}';

    protected $description = 'Import 2026 F&B price lists and fixed assets from client CSV files';

    private const DATA_DIR = 'database/data/price-list-2026';

    private const IMPORT_SORT_OFFSET = 100;

    /** @var array<string, int> */
    private array $chartAccountIds = [];

    private int $importSortBase = 0;

    private int $importCategoryIndex = 0;

    private int $lastCategorySortOrder = 0;

    /** @var array{categories_created: int, categories_updated: int, items_created: int, items_updated: int, assets_created: int, assets_updated: int} */
    private array $stats = [
        'categories_created' => 0,
        'categories_updated' => 0,
        'items_created' => 0,
        'items_updated' => 0,
        'assets_created' => 0,
        'assets_updated' => 0,
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

        $this->chartAccountIds = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->pluck('id', 'account_code')
            ->all();

        $this->importCategoryIndex = 0;
        $this->lastCategorySortOrder = 0;
        $this->importSortBase = $this->maxPreImportSortOrder() + self::IMPORT_SORT_OFFSET;

        $import = function () use ($hotel, $dryRun): void {
            $this->importSabaResto($dryRun);
            $this->importPrataCoffee($dryRun);
            $this->importLaundry($dryRun);
            $this->importFixedAssets($hotel->id, $dryRun);
        };

        if ($dryRun) {
            $import();
        } else {
            DB::transaction($import);
        }

        $this->newLine();
        $this->info('Import summary:');
        $this->line("  Menu categories created: {$this->stats['categories_created']}");
        $this->line("  Menu categories updated: {$this->stats['categories_updated']}");
        $this->line("  Menu items created: {$this->stats['items_created']}");
        $this->line("  Menu items updated: {$this->stats['items_updated']}");
        $this->line("  Assets created: {$this->stats['assets_created']}");
        $this->line("  Assets updated: {$this->stats['assets_updated']}");

        return self::SUCCESS;
    }

    private function importSabaResto(bool $dryRun): void
    {
        $path = base_path(self::DATA_DIR.'/saba-resto.csv');
        $rows = $this->readCsv($path);

        if ($rows === null) {
            return;
        }

        $currentGroup = null;

        foreach ($rows as $index => $row) {
            if ($index < 3 || $this->containsFilteredBy($row)) {
                continue;
            }

            if ($this->isHeaderRow($row, 'Item Description')) {
                continue;
            }

            $price = $this->parsePrice($row[8] ?? null);
            $groupName = trim((string) ($row[4] ?? ''));

            if ($price === 0.0 && $groupName !== '') {
                $currentGroup = $this->titleCaseGroupName($groupName);
                $categoryName = 'Saba Resto · '.$currentGroup;
                $this->upsertCategory($categoryName, $dryRun);

                continue;
            }

            $englishName = trim((string) ($row[5] ?? ''));
            $indonesianName = trim((string) ($row[7] ?? ''));
            $itemCode = trim((string) ($row[2] ?? ''));

            if ($price <= 0.0 || $currentGroup === null || $englishName === '') {
                continue;
            }

            $categoryName = 'Saba Resto · '.$currentGroup;
            $description = $this->buildDescription($indonesianName, $itemCode);

            $this->upsertMenuItem($categoryName, $englishName, $description, $price, $dryRun);
        }
    }

    private function importPrataCoffee(bool $dryRun): void
    {
        $path = base_path(self::DATA_DIR.'/prata-coffee.csv');
        $rows = $this->readCsv($path);

        if ($rows === null) {
            return;
        }

        $currentGroup = null;

        foreach ($rows as $index => $row) {
            if ($index < 2) {
                continue;
            }

            if ($this->isHeaderRow($row, 'Item Description')) {
                continue;
            }

            $name = $this->prataCoffeeName($row);
            $price = $this->parsePrice($this->firstNonEmptyCell($row, 1, 4));
            if ($name === '') {
                continue;
            }

            if ($this->isPrataCoffeeMenuTitle($name)) {
                continue;
            }

            if ($price === 0.0 && $this->isAllCapsGroupName($name)) {
                $currentGroup = $this->titleCaseGroupName($name);
                $categoryName = 'Prata Coffee · '.$currentGroup;
                $this->upsertCategory($categoryName, $dryRun);

                continue;
            }

            if ($price <= 0.0 || $currentGroup === null) {
                continue;
            }

            $categoryName = 'Prata Coffee · '.$currentGroup;
            $this->upsertMenuItem($categoryName, $name, null, $price, $dryRun);
        }
    }

    private function importLaundry(bool $dryRun): void
    {
        $path = base_path(self::DATA_DIR.'/laundry.csv');
        $rows = $this->readCsv($path);

        if ($rows === null) {
            return;
        }

        $categoryName = 'Laundry';
        $this->upsertCategory($categoryName, $dryRun);

        foreach ($rows as $index => $row) {
            if ($index < 2) {
                continue;
            }

            if ($this->isHeaderRow($row, 'Item Description')) {
                continue;
            }

            $englishName = trim((string) ($row[1] ?? ''));
            $indonesianName = trim((string) ($row[2] ?? ''));
            $price = $this->parsePrice($row[3] ?? null);

            if ($englishName === '' || $price <= 0.0) {
                continue;
            }

            if ($this->isHeaderRow($row, 'Item Description') || strcasecmp($englishName, 'Item Description') === 0) {
                continue;
            }

            $description = $indonesianName !== '' && $indonesianName !== $englishName
                ? $indonesianName
                : null;

            $this->upsertMenuItem($categoryName, $englishName, $description, $price, $dryRun);
        }
    }

    private function importFixedAssets(int $hotelId, bool $dryRun): void
    {
        $path = base_path(self::DATA_DIR.'/fixed-asset.csv');
        $rows = $this->readCsv($path);

        if ($rows === null) {
            return;
        }

        foreach ($rows as $index => $row) {
            if ($index < 2) {
                continue;
            }

            if ($this->isHeaderRow($row, 'Asset Code')) {
                continue;
            }

            $assetCode = trim((string) ($row[0] ?? ''));
            $name = trim((string) ($row[1] ?? ''));
            $assetTypeName = trim((string) ($row[2] ?? ''));
            $assetAccount = trim((string) ($row[3] ?? ''));
            $acquisitionCost = $this->parsePrice($row[4] ?? null);
            $purchaseDate = trim((string) ($row[6] ?? ''));
            $estimatedLife = trim((string) ($row[7] ?? ''));
            $depreciationMethod = trim((string) ($row[9] ?? ''));

            if ($assetCode === '' || $name === '') {
                continue;
            }

            $attributes = [
                'hotel_id' => $hotelId,
                'name' => $name,
                'asset_type' => $this->mapAssetType($assetTypeName)->value,
                'acquisition_cost' => $acquisitionCost,
                'acquisition_date' => $this->parseDate($purchaseDate),
                'useful_life_years' => $this->parseUsefulLifeYears($estimatedLife),
                'depreciation_method' => $this->mapDepreciationMethod($depreciationMethod)->value,
                'status' => AssetStatus::Operational->value,
                'chart_of_account_id' => $this->resolveChartOfAccountId($assetAccount),
            ];

            if ($dryRun) {
                $exists = Asset::query()
                    ->withoutGlobalScope('hotel')
                    ->where('asset_code', $assetCode)
                    ->exists();

                if ($exists) {
                    $this->stats['assets_updated']++;
                    $this->line("  Would update asset: {$assetCode} ({$name})");
                } else {
                    $this->stats['assets_created']++;
                    $this->line("  Would create asset: {$assetCode} ({$name})");
                }

                continue;
            }

            $asset = Asset::query()
                ->withoutGlobalScope('hotel')
                ->updateOrCreate(
                    ['asset_code' => $assetCode],
                    $attributes,
                );

            if ($asset->wasRecentlyCreated) {
                $this->stats['assets_created']++;
            } else {
                $this->stats['assets_updated']++;
            }
        }
    }

    private function upsertCategory(string $name, bool $dryRun): void
    {
        $sortOrder = $this->nextCategorySortOrder();

        if ($dryRun) {
            $category = MenuCategory::query()->where('name', $name)->first();

            if ($category === null) {
                $this->stats['categories_created']++;
                $this->line("  Would create category: {$name}");
            } else {
                $this->stats['categories_updated']++;
            }

            return;
        }

        $category = MenuCategory::query()->updateOrCreate(
            ['name' => $name],
            ['sort_order' => $sortOrder],
        );

        if ($category->wasRecentlyCreated) {
            $this->stats['categories_created']++;
        } else {
            $this->stats['categories_updated']++;
        }
    }

    private function upsertMenuItem(
        string $categoryName,
        string $name,
        ?string $description,
        float $price,
        bool $dryRun,
    ): void {
        if ($dryRun) {
            $category = MenuCategory::query()->where('name', $categoryName)->first();

            if ($category === null) {
                $this->stats['items_created']++;

                return;
            }

            $exists = MenuItem::query()
                ->where('menu_category_id', $category->id)
                ->where('name', $name)
                ->exists();

            if ($exists) {
                $this->stats['items_updated']++;
            } else {
                $this->stats['items_created']++;
            }

            return;
        }

        $category = MenuCategory::query()->firstOrCreate(
            ['name' => $categoryName],
            ['sort_order' => $this->lastCategorySortOrder > 0
                ? $this->lastCategorySortOrder
                : $this->nextCategorySortOrder()],
        );

        $item = MenuItem::query()->updateOrCreate(
            [
                'menu_category_id' => $category->id,
                'name' => $name,
            ],
            [
                'description' => $description,
                'price' => $price,
                'is_available' => true,
            ],
        );

        if ($item->wasRecentlyCreated) {
            $this->stats['items_created']++;
        } else {
            $this->stats['items_updated']++;
        }
    }

    /**
     * @return list<array<int, string|null>>|null
     */
    private function readCsv(string $path): ?array
    {
        if (! is_readable($path)) {
            $this->error('CSV file not found: '.$path);

            return null;
        }

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

    /**
     * @param  array<int, string|null>  $row
     */
    private function containsFilteredBy(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && str_contains((string) $value, 'Filtered By')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isHeaderRow(array $row, string $needle): bool
    {
        foreach ($row as $value) {
            if ($value !== null && str_contains((string) $value, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function prataCoffeeName(array $row): string
    {
        return $this->firstNonEmptyCell($row, 0, 2, 3);
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function firstNonEmptyCell(array $row, int ...$indices): string
    {
        foreach ($indices as $index) {
            $value = trim((string) ($row[$index] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function isPrataCoffeeMenuTitle(string $name): bool
    {
        return str_starts_with(strtoupper($name), 'MENU PRATA COFFEE');
    }

    private function isAllCapsGroupName(string $name): bool
    {
        $letters = preg_replace('/[^A-Za-z]/', '', $name) ?? '';

        if ($letters === '') {
            return false;
        }

        return strtoupper($letters) === $letters;
    }

    private function titleCaseGroupName(string $name): string
    {
        return Str::title(Str::lower(trim($name)));
    }

    private function buildDescription(string $indonesianName, string $itemCode): ?string
    {
        $description = $indonesianName;

        if ($itemCode !== '' && ! str_starts_with($itemCode, 'F&B-')) {
            $description = $description !== ''
                ? "{$description} ({$itemCode})"
                : $itemCode;
        }

        return $description !== '' ? $description : null;
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

    private function parseUsefulLifeYears(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        if (preg_match('/(\d+)\s*Yr/i', $value, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function mapAssetType(string $value): AssetType
    {
        return match (strtolower(trim($value))) {
            'other inventory' => AssetType::OtherInventory,
            'equipment' => AssetType::Equipment,
            'office equipment' => AssetType::OfficeEquipment,
            'house keeping' => AssetType::Housekeeping,
            'kitchen set' => AssetType::KitchenSet,
            'office machinery' => AssetType::OfficeMachinery,
            'furniture' => AssetType::Furniture,
            'building' => AssetType::Building,
            'machinery' => AssetType::Machinery,
            'ship' => AssetType::Ship,
            'vehicle' => AssetType::Vehicle,
            default => AssetType::Other,
        };
    }

    private function mapDepreciationMethod(string $value): DepreciationMethod
    {
        if (strcasecmp(trim($value), 'Straight Line Method') === 0) {
            return DepreciationMethod::StraightLine;
        }

        return DepreciationMethod::DoubleDeclining;
    }

    private function maxPreImportSortOrder(): int
    {
        return (int) MenuCategory::query()
            ->whereNotLike('name', 'Saba Resto · %')
            ->whereNotLike('name', 'Prata Coffee · %')
            ->where('name', '!=', 'Laundry')
            ->max('sort_order');
    }

    private function nextCategorySortOrder(): int
    {
        $this->importCategoryIndex++;
        $this->lastCategorySortOrder = $this->importSortBase + $this->importCategoryIndex;

        return $this->lastCategorySortOrder;
    }

    private function resolveChartOfAccountId(string $assetAccount): ?int
    {
        if ($assetAccount === '') {
            return null;
        }

        $candidates = array_unique([
            $assetAccount,
            (string) (int) $this->parsePrice($assetAccount),
        ]);

        foreach ($candidates as $candidate) {
            if (isset($this->chartAccountIds[$candidate])) {
                return $this->chartAccountIds[$candidate];
            }
        }

        return null;
    }
}
