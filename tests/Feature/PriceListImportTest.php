<?php

namespace Tests\Feature;

use App\Enums\AssetType;
use App\Enums\DepreciationMethod;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use Database\Seeders\MenuCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceListImportTest extends TestCase
{
    use RefreshDatabase;

    private const LAUNDRY_CSV = 'database/data/price-list-2026/laundry.csv';

    private Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::query()->create([
            'name' => 'Pratasaba Resort',
            'code' => 'PRS',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        ChartOfAccount::query()->create([
            'hotel_id' => $this->hotel->id,
            'account_code' => '1400500',
            'name' => 'Machinery',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('pratasaba:import-price-list', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, MenuCategory::query()->count());
        $this->assertSame(0, MenuItem::query()->count());
        $this->assertSame(0, Asset::query()->withoutGlobalScope('hotel')->count());
    }

    public function test_import_creates_expected_menu_categories_and_items(): void
    {
        $this->artisan('pratasaba:import-price-list')
            ->assertSuccessful();

        $mealsCategory = MenuCategory::query()
            ->where('name', 'Saba Resto · Meals Packages')
            ->first();

        $this->assertNotNull($mealsCategory);
        $this->assertSame(101, $mealsCategory->sort_order);

        $lunchPackage = MenuItem::query()
            ->where('menu_category_id', $mealsCategory->id)
            ->where('name', 'LUNCH PACKAGES')
            ->first();

        $this->assertNotNull($lunchPackage);
        $this->assertSame('200000.00', $lunchPackage->price);
        $this->assertSame('PAKET MAKAN SIANG (Pack-001)', $lunchPackage->description);
        $this->assertTrue($lunchPackage->is_available);

        $espressoCategory = MenuCategory::query()
            ->where('name', 'Prata Coffee · Espresso Base')
            ->first();

        $this->assertNotNull($espressoCategory);

        $solo = MenuItem::query()
            ->where('menu_category_id', $espressoCategory->id)
            ->where('name', 'Solo')
            ->first();

        $this->assertNotNull($solo);
        $this->assertSame('40000.00', $solo->price);

        $laundryCategory = MenuCategory::query()
            ->where('name', 'Laundry')
            ->first();

        $this->assertNotNull($laundryCategory);

        $jacket = MenuItem::query()
            ->where('menu_category_id', $laundryCategory->id)
            ->where('name', 'JACKET')
            ->first();

        $this->assertNotNull($jacket);
        $this->assertSame('50000.00', $jacket->price);
        $this->assertSame('JAKET', $jacket->description);
    }

    public function test_imported_categories_sort_after_seeded_categories(): void
    {
        $this->seed(MenuCategorySeeder::class);

        $this->artisan('pratasaba:import-price-list')->assertSuccessful();

        $orderedNames = MenuCategory::query()
            ->orderBy('sort_order')
            ->pluck('name')
            ->all();

        $seededNames = ['Appetizers', 'Main Courses', 'Soups', 'Beverages', 'Desserts'];
        $importedNames = array_values(array_filter(
            $orderedNames,
            static fn (string $name): bool => ! in_array($name, $seededNames, true),
        ));

        $this->assertNotEmpty($importedNames);

        $lastSeededIndex = max(array_map(
            static fn (string $name): int|false => array_search($name, $orderedNames, true),
            $seededNames,
        ));

        $firstImportedIndex = array_search($importedNames[0], $orderedNames, true);

        $this->assertGreaterThan($lastSeededIndex, $firstImportedIndex);

        $mealsCategory = MenuCategory::query()
            ->where('name', 'Saba Resto · Meals Packages')
            ->first();

        $this->assertNotNull($mealsCategory);
        $this->assertSame(106, $mealsCategory->sort_order);
    }

    public function test_running_import_twice_repairs_category_sort_order(): void
    {
        $this->seed(MenuCategorySeeder::class);

        $this->artisan('pratasaba:import-price-list')->assertSuccessful();

        MenuCategory::query()
            ->where('name', 'Saba Resto · Meals Packages')
            ->update(['sort_order' => 2]);

        $this->artisan('pratasaba:import-price-list')->assertSuccessful();

        $mealsCategory = MenuCategory::query()
            ->where('name', 'Saba Resto · Meals Packages')
            ->first();

        $this->assertNotNull($mealsCategory);
        $this->assertSame(106, $mealsCategory->sort_order);
    }

    public function test_running_import_twice_does_not_duplicate_rows(): void
    {
        $this->artisan('pratasaba:import-price-list')->assertSuccessful();

        $categoryCount = MenuCategory::query()->count();
        $itemCount = MenuItem::query()->count();
        $assetCount = Asset::query()->withoutGlobalScope('hotel')->count();

        $this->artisan('pratasaba:import-price-list')->assertSuccessful();

        $this->assertSame($categoryCount, MenuCategory::query()->count());
        $this->assertSame($itemCount, MenuItem::query()->count());
        $this->assertSame($assetCount, Asset::query()->withoutGlobalScope('hotel')->count());
    }

    public function test_changed_price_updates_existing_item(): void
    {
        $path = base_path(self::LAUNDRY_CSV);
        $original = file_get_contents($path);

        $this->artisan('pratasaba:import-price-list')->assertSuccessful();

        $laundryCategory = MenuCategory::query()->where('name', 'Laundry')->firstOrFail();
        $jacket = MenuItem::query()
            ->where('menu_category_id', $laundryCategory->id)
            ->where('name', 'JACKET')
            ->firstOrFail();

        $this->assertSame('50000.00', $jacket->price);

        file_put_contents($path, str_replace('50000.0', '55000.0', $original));

        try {
            $this->artisan('pratasaba:import-price-list')->assertSuccessful();

            $jacket->refresh();

            $this->assertSame('55000.00', $jacket->price);
            $this->assertSame(1, MenuItem::query()
                ->where('menu_category_id', $laundryCategory->id)
                ->where('name', 'JACKET')
                ->count());
        } finally {
            file_put_contents($path, $original);
        }
    }

    public function test_assets_import_with_asset_code_and_parsed_useful_life(): void
    {
        $this->artisan('pratasaba:import-price-list')->assertSuccessful();

        $asset = Asset::query()
            ->withoutGlobalScope('hotel')
            ->where('asset_code', 'PAA-0001')
            ->first();

        $this->assertNotNull($asset);
        $this->assertSame('Deep Fryer Penggorengan Gas GF-20-FS', $asset->name);
        $this->assertSame(AssetType::Machinery, $asset->asset_type);
        $this->assertSame('11100000.00', $asset->acquisition_cost);
        $this->assertSame('2022-09-30', $asset->acquisition_date?->toDateString());
        $this->assertSame(8, $asset->useful_life_years);
        $this->assertSame(DepreciationMethod::StraightLine, $asset->depreciation_method);
        $this->assertSame($this->hotel->id, $asset->hotel_id);
        $this->assertNotNull($asset->chart_of_account_id);
    }
}
