<?php

namespace Tests\Feature;

use App\Enums\DivePackageType;
use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\FolioType;
use App\Models\DivePackage;
use App\Models\DiveRateItem;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\RevenueCategory;
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

class FolioChargeTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $cashier;

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

        $this->cashier = User::factory()->create(['hotel_id' => null]);
        $this->cashier->assignRole('front_office');
        $this->hotel->users()->attach($this->cashier->id);

        session(['current_hotel_id' => $this->hotel->id]);

        $guest = Guest::query()->create(['full_name' => 'Charge Guest']);
        $this->folio = Folio::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_no' => 'FOL-CHARGE-0001',
            'guest_id' => $guest->id,
            'type' => FolioType::Master->value,
            'status' => FolioStatus::Open->value,
            'opened_at' => now(),
        ]);
    }

    public function test_manual_charge_posts_misc_folio_item_with_no_tax_and_no_service_charge(): void
    {
        $response = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'description' => 'Extra towels',
            'quantity' => 2,
            'unit_price' => 250_000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $item = FolioItem::query()->where('folio_id', $this->folio->id)->first();
        $this->assertNotNull($item);
        $this->assertSame(FolioItemType::Misc, $item->item_type);
        $this->assertSame('manual_charge', $item->reference_type);
        $this->assertEquals(500_000, (float) $item->amount);
        $this->assertEquals(0, (float) $item->service_charge_amount);
        $this->assertEquals(0, (float) $item->tax_amount);
        $this->assertEquals(500_000, $item->line_total);
        $this->assertSame('Extra towels', $item->description);
    }

    public function test_dive_package_derives_description_and_dive_center_revenue_category(): void
    {
        $divePackage = DivePackage::query()->create([
            'hotel_id' => $this->hotel->id,
            'code' => 'DP-2D',
            'name' => 'Two Tank Dive',
            'type' => DivePackageType::NightDive->value,
            'price_per_person' => 1_200_000,
            'min_pax' => 1,
            'is_active' => true,
        ]);

        $diveCenterCategory = RevenueCategory::query()
            ->where('hotel_id', $this->hotel->id)
            ->where('code', 'dive_center')
            ->firstOrFail();

        $response = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'description' => 'ignored by backend',
            'quantity' => 1,
            'unit_price' => 1_200_000,
            'dive_package_id' => $divePackage->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $item = FolioItem::query()->where('folio_id', $this->folio->id)->firstOrFail();
        $this->assertSame('Dive: Two Tank Dive', $item->description);
        $this->assertSame($diveCenterCategory->id, $item->revenue_category_id);
    }

    public function test_closed_folio_charge_returns_error_flash(): void
    {
        $this->folio->update([
            'status' => FolioStatus::Closed->value,
            'closed_at' => now(),
        ]);

        $response = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'description' => 'Late charge',
            'quantity' => 1,
            'unit_price' => 100_000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Cannot post charges to a closed or voided folio.');
        $this->assertSame(0, FolioItem::query()->where('folio_id', $this->folio->id)->count());
    }

    public function test_user_without_billing_post_is_forbidden(): void
    {
        $manager = User::factory()->create(['hotel_id' => null]);
        $manager->assignRole('manager');
        $this->hotel->users()->attach($manager->id);

        $response = $this->actingAs($manager)->post(route('folios.charges.store', $this->folio), [
            'description' => 'Unauthorized',
            'quantity' => 1,
            'unit_price' => 50_000,
        ]);

        $response->assertForbidden();
    }

    public function test_show_page_passes_no_tax_rules_for_non_taxable_misc_charge_preview(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('folios.show', $this->folio));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Folios/Show')
            ->has('miscChargeTaxRules', 0)
        );
    }

    public function test_show_page_exposes_dive_boat_routes_with_engine_prices(): void
    {
        $this->artisan('pratasaba:import-dive-price-list')->assertSuccessful();

        $response = $this->actingAs($this->cashier)->get(route('folios.show', $this->folio));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Folios/Show')
            ->has('diveBoatRoutes', 6)
            ->where('diveBoatRoutes.0.route', 'House Reef')
            ->has('diveBoatRoutes.0.boat_prices', 2)
            ->where('diveBoatRoutes.0.boat_prices.0.price', 2_000_000)
            ->where('diveBoatRoutes.0.boat_prices.1.price', 3_000_000)
        );
    }

    public function test_licensed_dive_package_charge_with_route_and_boat_has_no_tax_or_service_charge(): void
    {
        $this->artisan('pratasaba:import-dive-price-list')->assertSuccessful();

        $divePackage = DivePackage::query()
            ->where('hotel_id', $this->hotel->id)
            ->where('code', 'DV-PKG-GRP')
            ->firstOrFail();

        $boatRate = DiveRateItem::query()->where('code', 'BOAT-HOUSE-REEF-200HP')->firstOrFail();

        $response = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'description' => 'ignored',
            'quantity' => 2,
            'unit_price' => 2_500_000,
            'dive_package_id' => $divePackage->id,
            'dive_boat_rate_item_id' => $boatRate->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $item = FolioItem::query()->where('folio_id', $this->folio->id)->firstOrFail();
        $this->assertSame('Dive: Dive Package (Group) · House Reef', $item->description);
        $this->assertEquals(5_000_000, (float) $item->amount);
        $this->assertEquals(0, (float) $item->service_charge_amount);
        $this->assertEquals(0, (float) $item->tax_amount);
        $this->assertEquals(5_000_000, $item->line_total);
    }

    public function test_show_page_exposes_daily_trip_rate_items_after_import(): void
    {
        $this->artisan('pratasaba:import-dive-price-list')->assertSuccessful();

        $response = $this->actingAs($this->cashier)->get(route('folios.show', $this->folio));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Folios/Show')
            ->has('dailyTripDestinations', 11)
            ->where('dailyTripDestinations.0.destination', 'Berau-Maratua')
            ->has('dailyTripRentals', 5)
            ->where('dailyTripGuide.code', 'DT-GUIDE')
            ->where('dailyTripGuide.price', 600_000)
            ->where('dailyTripGuide.name', 'Additional Guide — Trip or Diving (per day)')
            ->has('guideChargeUnits', 3)
            ->where('guideChargeUnits.0.value', 'per_day')
            ->where('guideChargeUnits.1.value', 'half_day')
            ->where('guideChargeUnits.2.value', 'per_trip')
        );
    }

    public function test_daily_trip_and_rental_charges_have_no_tax_or_service_charge(): void
    {
        $this->artisan('pratasaba:import-dive-price-list')->assertSuccessful();

        $tripRate = DiveRateItem::query()->where('code', 'DT-KAKABAN-MEDIUM')->firstOrFail();
        $rentalRate = DiveRateItem::query()->where('code', 'DT-RENT-WETSUIT')->firstOrFail();

        $tripResponse = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'description' => 'ignored',
            'quantity' => 1,
            'unit_price' => 1,
            'rate_item_id' => $tripRate->id,
        ]);
        $tripResponse->assertRedirect();
        $tripResponse->assertSessionHas('success');

        $tripItem = FolioItem::query()->where('folio_id', $this->folio->id)->firstOrFail();
        $this->assertSame($tripRate->name, $tripItem->description);
        $this->assertEquals(2_500_000, (float) $tripItem->amount);
        $this->assertEquals(0, (float) $tripItem->service_charge_amount);
        $this->assertEquals(0, (float) $tripItem->tax_amount);
        $this->assertEquals(2_500_000, $tripItem->line_total);

        $rentalResponse = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'description' => 'ignored',
            'quantity' => 3,
            'unit_price' => 1,
            'rate_item_id' => $rentalRate->id,
        ]);
        $rentalResponse->assertRedirect();
        $rentalResponse->assertSessionHas('success');

        $rentalItem = FolioItem::query()
            ->where('folio_id', $this->folio->id)
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame($rentalRate->name, $rentalItem->description);
        $this->assertEquals(300_000, (float) $rentalItem->amount);
        $this->assertEquals(0, (float) $rentalItem->service_charge_amount);
        $this->assertEquals(0, (float) $rentalItem->tax_amount);
        $this->assertEquals(300_000, $rentalItem->line_total);
    }

    public function test_pratasaba_rental_motor_camera_guide_and_car_charges_have_no_tax_or_service_charge(): void
    {
        $this->artisan('pratasaba:import-dive-price-list')->assertSuccessful();

        $motorRate = DiveRateItem::query()->where('code', 'DT-RENT-MOTOR')->firstOrFail();
        $cameraRate = DiveRateItem::query()->where('code', 'DT-RENT-UNDERWATER-CAMERA')->firstOrFail();
        $guideRate = DiveRateItem::query()->where('code', 'DT-GUIDE')->firstOrFail();

        $transportCarCategory = RevenueCategory::query()
            ->where('hotel_id', $this->hotel->id)
            ->where('code', 'transport_car')
            ->firstOrFail();

        $motorResponse = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'description' => 'ignored',
            'quantity' => 2,
            'unit_price' => 1,
            'rate_item_id' => $motorRate->id,
        ]);
        $motorResponse->assertRedirect();
        $motorResponse->assertSessionHas('success');

        $motorItem = FolioItem::query()->where('folio_id', $this->folio->id)->firstOrFail();
        $this->assertSame($motorRate->name, $motorItem->description);
        $this->assertEquals(400_000, (float) $motorItem->amount);
        $this->assertEquals(0, (float) $motorItem->service_charge_amount);
        $this->assertEquals(0, (float) $motorItem->tax_amount);

        $cameraResponse = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'description' => 'ignored',
            'quantity' => 1,
            'unit_price' => 1,
            'rate_item_id' => $cameraRate->id,
        ]);
        $cameraResponse->assertRedirect();
        $cameraResponse->assertSessionHas('success');

        $cameraItem = FolioItem::query()
            ->where('folio_id', $this->folio->id)
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertEquals(350_000, (float) $cameraItem->amount);
        $this->assertEquals(0, (float) $cameraItem->service_charge_amount);
        $this->assertEquals(0, (float) $cameraItem->tax_amount);

        $guideResponse = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'charge_item_group' => 'guide',
            'guide_unit' => 'per_day',
            'description' => 'ignored',
            'quantity' => 1,
            'unit_price' => 1,
            'rate_item_id' => $guideRate->id,
        ]);
        $guideResponse->assertRedirect();
        $guideResponse->assertSessionHas('success');

        $guideItem = FolioItem::query()
            ->where('folio_id', $this->folio->id)
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame(
            'Additional Guide — Trip or Diving (per day)',
            $guideItem->description,
        );
        $this->assertEquals(600_000, (float) $guideItem->amount);
        $this->assertEquals(0, (float) $guideItem->service_charge_amount);
        $this->assertEquals(0, (float) $guideItem->tax_amount);

        $carResponse = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'charge_item_group' => 'car_rental',
            'description' => 'Avanza, Tanjung Batu–Berau, 1 day',
            'quantity' => 1,
            'unit_price' => 1_250_000,
        ]);
        $carResponse->assertRedirect();
        $carResponse->assertSessionHas('success');

        $carItem = FolioItem::query()
            ->where('folio_id', $this->folio->id)
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame('Avanza, Tanjung Batu–Berau, 1 day', $carItem->description);
        $this->assertEquals(1_250_000, (float) $carItem->amount);
        $this->assertEquals(0, (float) $carItem->service_charge_amount);
        $this->assertEquals(0, (float) $carItem->tax_amount);
        $this->assertSame($transportCarCategory->id, $carItem->revenue_category_id);

        $rejectedCar = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'charge_item_group' => 'car_rental',
            'description' => 'Invalid zero price',
            'quantity' => 1,
            'unit_price' => 0,
        ]);
        $rejectedCar->assertSessionHasErrors('unit_price');
    }

    public function test_guide_charge_units_use_rate_item_or_operator_price_with_distinct_descriptions(): void
    {
        $this->artisan('pratasaba:import-dive-price-list')->assertSuccessful();

        $guideRate = DiveRateItem::query()->where('code', 'DT-GUIDE')->firstOrFail();
        $boatCategory = RevenueCategory::query()
            ->where('hotel_id', $this->hotel->id)
            ->where('code', 'boat')
            ->firstOrFail();

        $perDayResponse = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'charge_item_group' => 'guide',
            'guide_unit' => 'per_day',
            'description' => 'client text',
            'quantity' => 2,
            'unit_price' => 1,
            'rate_item_id' => $guideRate->id,
            'revenue_category_id' => $boatCategory->id,
        ]);
        $perDayResponse->assertRedirect();
        $perDayResponse->assertSessionHas('success');

        $perDayItem = FolioItem::query()->where('folio_id', $this->folio->id)->firstOrFail();
        $this->assertSame('Additional Guide — Trip or Diving (per day)', $perDayItem->description);
        $this->assertEquals(1_200_000, (float) $perDayItem->amount);
        $this->assertEquals(0, (float) $perDayItem->service_charge_amount);
        $this->assertEquals(0, (float) $perDayItem->tax_amount);
        $this->assertSame($boatCategory->id, $perDayItem->revenue_category_id);

        $halfDayResponse = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'charge_item_group' => 'guide',
            'guide_unit' => 'half_day',
            'description' => 'client text',
            'quantity' => 1,
            'unit_price' => 450_000,
            'rate_item_id' => $guideRate->id,
            'revenue_category_id' => $boatCategory->id,
        ]);
        $halfDayResponse->assertRedirect();
        $halfDayResponse->assertSessionHas('success');

        $halfDayItem = FolioItem::query()
            ->where('folio_id', $this->folio->id)
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame('Additional Guide — Trip or Diving (half day)', $halfDayItem->description);
        $this->assertEquals(450_000, (float) $halfDayItem->amount);
        $this->assertEquals(0, (float) $halfDayItem->service_charge_amount);
        $this->assertEquals(0, (float) $halfDayItem->tax_amount);
        $this->assertSame($boatCategory->id, $halfDayItem->revenue_category_id);

        $perTripResponse = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'charge_item_group' => 'guide',
            'guide_unit' => 'per_trip',
            'description' => 'client text',
            'quantity' => 1,
            'unit_price' => 800_000,
            'rate_item_id' => $guideRate->id,
            'revenue_category_id' => $boatCategory->id,
        ]);
        $perTripResponse->assertRedirect();
        $perTripResponse->assertSessionHas('success');

        $perTripItem = FolioItem::query()
            ->where('folio_id', $this->folio->id)
            ->orderByDesc('id')
            ->firstOrFail();
        $this->assertSame('Additional Guide — Trip or Diving (per trip)', $perTripItem->description);
        $this->assertEquals(800_000, (float) $perTripItem->amount);
        $this->assertEquals(0, (float) $perTripItem->service_charge_amount);
        $this->assertEquals(0, (float) $perTripItem->tax_amount);
        $this->assertSame($boatCategory->id, $perTripItem->revenue_category_id);

        $this->assertNotSame($perDayItem->description, $halfDayItem->description);
        $this->assertNotSame($perDayItem->description, $perTripItem->description);
        $this->assertNotSame($halfDayItem->description, $perTripItem->description);

        $rejectedHalfDay = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'charge_item_group' => 'guide',
            'guide_unit' => 'half_day',
            'description' => 'missing price',
            'quantity' => 1,
            'unit_price' => 0,
            'rate_item_id' => $guideRate->id,
        ]);
        $rejectedHalfDay->assertSessionHasErrors('unit_price');

        $rejectedPerTrip = $this->actingAs($this->cashier)->post(route('folios.charges.store', $this->folio), [
            'charge_item_group' => 'guide',
            'guide_unit' => 'per_trip',
            'description' => 'missing price',
            'quantity' => 1,
            'rate_item_id' => $guideRate->id,
        ]);
        $rejectedPerTrip->assertSessionHasErrors('unit_price');
    }

    public function test_active_rules_payload_for_item_type_still_returns_rules_for_taxable_room_charges(): void
    {
        $taxCalculator = app(TaxCalculator::class);

        $rules = $taxCalculator->activeRulesPayloadForItemType(FolioItemType::Room->value);

        $this->assertNotEmpty($rules);
    }
}
