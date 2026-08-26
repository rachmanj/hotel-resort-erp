<?php

namespace Tests\Feature;

use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\FolioType;
use App\Models\Department;
use App\Models\Folio;
use App\Models\GeneralLedger;
use App\Models\Guest;
use App\Models\Hotel;
use App\Services\FolioPostingService;
use Database\Seeders\AccountingDemoSeeder;
use Database\Seeders\BillingDemoSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DepartmentGlPostingTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDemoSeeder::class);
        $this->seed(DepartmentSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Test Hotel',
            'code' => 'TST',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $this->hotel->id]);

        (new ChartOfAccountsSeeder)->forHotel($this->hotel);
        (new AccountingDemoSeeder)->run();
    }

    public function test_folio_charge_posts_department_id_to_general_ledger(): void
    {
        $kitchen = Department::query()
            ->withoutGlobalScope('hotel')
            ->whereNull('hotel_id')
            ->where('code', 'kitchen')
            ->firstOrFail();

        $guest = Guest::query()->create(['full_name' => 'GL Dept Guest']);

        $folio = Folio::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_no' => 'FOL-TEST-0001',
            'guest_id' => $guest->id,
            'type' => FolioType::Master->value,
            'status' => FolioStatus::Open->value,
            'opened_at' => now(),
        ]);

        $folioItem = app(FolioPostingService::class)->postCharge(
            $folio,
            FolioItemType::Fb->value,
            'Restaurant dinner',
            500000,
            1,
            null,
            null,
            null,
            false,
            null,
            $kitchen->id,
        );

        $this->assertSame($kitchen->id, $folioItem->department_id);

        $glRows = GeneralLedger::query()
            ->withoutGlobalScope('hotel')
            ->where('source_type', 'folio_item')
            ->where('source_id', $folioItem->id)
            ->get();

        $this->assertGreaterThan(0, $glRows->count());
        $this->assertTrue($glRows->every(fn (GeneralLedger $row): bool => $row->department_id === $kitchen->id));
    }

    public function test_folio_charge_auto_assigns_department_by_item_type(): void
    {
        $frontOffice = Department::query()
            ->withoutGlobalScope('hotel')
            ->whereNull('hotel_id')
            ->where('code', 'front_office')
            ->firstOrFail();

        $guest = Guest::query()->create(['full_name' => 'Auto Dept Guest']);

        $folio = Folio::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_no' => 'FOL-TEST-0002',
            'guest_id' => $guest->id,
            'type' => FolioType::Master->value,
            'status' => FolioStatus::Open->value,
            'opened_at' => now(),
        ]);

        $folioItem = app(FolioPostingService::class)->postCharge(
            $folio,
            FolioItemType::Room->value,
            'Room charge',
            1000000,
            1,
            null,
            null,
            null,
            false,
        );

        $this->assertSame($frontOffice->id, $folioItem->department_id);
    }
}
