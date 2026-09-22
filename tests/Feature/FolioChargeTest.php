<?php

namespace Tests\Feature;

use App\Enums\DivePackageType;
use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\FolioType;
use App\Models\DivePackage;
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
            'type' => DivePackageType::DivePackage->value,
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

    public function test_active_rules_payload_for_item_type_still_returns_rules_for_taxable_room_charges(): void
    {
        $taxCalculator = app(TaxCalculator::class);

        $rules = $taxCalculator->activeRulesPayloadForItemType(FolioItemType::Room->value);

        $this->assertNotEmpty($rules);
    }
}
