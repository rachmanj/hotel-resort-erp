<?php

namespace Tests\Feature;

use App\Enums\BoatCharterStatus;
use App\Enums\BoatCharterType;
use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\FolioType;
use App\Enums\GuideType;
use App\Models\BoatCharter;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\User;
use App\Services\TaxCalculator;
use Database\Seeders\AccountingDemoSeeder;
use Database\Seeders\BillingDemoSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RevenueCategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BoatCharterBillingTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $admin;

    private Folio $folio;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDemoSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Test Hotel',
            'code' => 'TST',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        (new RevenueCategorySeeder)->run();
        (new ChartOfAccountsSeeder)->forHotel($this->hotel);
        (new AccountingDemoSeeder)->run();

        $this->admin = User::factory()->create(['hotel_id' => null]);
        $this->admin->assignRole('admin');
        $this->hotel->users()->attach($this->admin->id);

        session(['current_hotel_id' => $this->hotel->id]);

        $guest = Guest::query()->create(['full_name' => 'Dive Guest']);
        $this->folio = Folio::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_no' => 'FOL-DIVE-0001',
            'guest_id' => $guest->id,
            'type' => FolioType::Master->value,
            'status' => FolioStatus::Open->value,
            'opened_at' => now(),
        ]);
    }

    public function test_boat_charter_bill_posts_folio_item_with_no_tax_and_no_service_charge(): void
    {
        $boatCharter = BoatCharter::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_id' => $this->folio->id,
            'trip_date' => now()->toDateString(),
            'destination' => 'Sangalaki',
            'charter_type' => BoatCharterType::Diving->value,
            'price' => 1_500_000,
            'quantity' => 2,
            'guide_type' => GuideType::Employee->value,
            'status' => BoatCharterStatus::Confirmed->value,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.boat-charters.bill', $boatCharter));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $boatCharter->refresh();
        $this->assertSame(BoatCharterStatus::Billed, $boatCharter->status);
        $this->assertNotNull($boatCharter->folio_item_id);

        $item = FolioItem::query()->findOrFail($boatCharter->folio_item_id);
        $this->assertSame(FolioItemType::Misc, $item->item_type);
        $this->assertEquals(3_000_000, (float) $item->amount);
        $this->assertEquals(0, (float) $item->tax_amount);
        $this->assertEquals(0, (float) $item->service_charge_amount);
        $this->assertEquals(3_000_000, $item->line_total);
    }

    public function test_index_page_passes_no_tax_rules_for_non_taxable_misc_charge_preview(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.boat-charters.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/BoatCharters/Index')
            ->has('miscChargeTaxRules', 0)
        );
    }

    public function test_active_rules_payload_for_item_type_still_returns_rules_for_taxable_fb_charges(): void
    {
        $taxCalculator = app(TaxCalculator::class);

        $rules = $taxCalculator->activeRulesPayloadForItemType(FolioItemType::Fb->value);

        $this->assertNotEmpty($rules);
    }
}
