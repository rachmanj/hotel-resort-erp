<?php

namespace Tests\Feature;

use App\Enums\BoatEngineOption;
use App\Enums\DailyTripBoatClass;
use App\Enums\DiveRateItemType;
use App\Models\DivePackage;
use App\Models\DiveRateItem;
use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivePriceListImportTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('pratasaba:import-dive-price-list', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, DiveRateItem::query()->count());
        $this->assertSame(0, DivePackage::query()->count());
    }

    public function test_import_is_idempotent_and_prices_match_client_list(): void
    {
        $this->artisan('pratasaba:import-dive-price-list')->assertSuccessful();
        $firstRateCount = DiveRateItem::query()->count();
        $firstPackageCount = DivePackage::query()->count();

        $this->artisan('pratasaba:import-dive-price-list')->assertSuccessful();

        $this->assertSame($firstRateCount, DiveRateItem::query()->count());
        $this->assertSame($firstPackageCount, DivePackage::query()->count());

        $soloPackage = DivePackage::query()
            ->where('hotel_id', $this->hotel->id)
            ->where('code', 'DV-PKG-SOLO')
            ->first();

        $this->assertNotNull($soloPackage);
        $this->assertSame('2500000.00', $soloPackage->price_per_person);

        $groupPackage = DivePackage::query()
            ->where('hotel_id', $this->hotel->id)
            ->where('code', 'DV-PKG-GRP')
            ->first();

        $this->assertNotNull($groupPackage);
        $this->assertSame('1500000.00', $groupPackage->price_per_person);

        $houseReef200 = DiveRateItem::query()->where('code', 'BOAT-HOUSE-REEF-200HP')->first();
        $this->assertNotNull($houseReef200);
        $this->assertSame(DiveRateItemType::BoatRent, $houseReef200->item_type);
        $this->assertSame('House Reef', $houseReef200->route);
        $this->assertSame(BoatEngineOption::Hp200, $houseReef200->boat_engine_option);
        $this->assertSame('2000000.00', $houseReef200->price);

        $nunukanTwin = DiveRateItem::query()->where('code', 'BOAT-NUNUKAN-400HP')->first();
        $this->assertNotNull($nunukanTwin);
        $this->assertSame('5000000.00', $nunukanTwin->price);

        $nightDive = DiveRateItem::query()->where('code', 'DV-NIGHT')->first();
        $this->assertNotNull($nightDive);
        $this->assertSame('600000.00', $nightDive->price);

        $kakabanSmall = DiveRateItem::query()->where('code', 'DT-KAKABAN-SMALL')->first();
        $this->assertNotNull($kakabanSmall);
        $this->assertSame(DiveRateItemType::DailyTrip, $kakabanSmall->item_type);
        $this->assertSame('Kakaban', $kakabanSmall->route);
        $this->assertSame(DailyTripBoatClass::Small40Pk, $kakabanSmall->boat_class);
        $this->assertSame('2000000.00', $kakabanSmall->price);

        $sangalakiMedium = DiveRateItem::query()->where('code', 'DT-SANGALAKI-MEDIUM')->first();
        $this->assertNotNull($sangalakiMedium);
        $this->assertSame('3500000.00', $sangalakiMedium->price);

        $talisayan = DiveRateItem::query()->where('code', 'DT-TALISAYAN-MEDIUM')->first();
        $this->assertNotNull($talisayan);
        $this->assertSame('7500000.00', $talisayan->price);

        $maskSnorkel = DiveRateItem::query()->where('code', 'DT-RENT-MASK-SNORKEL')->first();
        $this->assertNotNull($maskSnorkel);
        $this->assertSame(DiveRateItemType::DailyTripRental, $maskSnorkel->item_type);
        $this->assertSame('50000.00', $maskSnorkel->price);

        $guide = DiveRateItem::query()->where('code', 'DT-GUIDE')->first();
        $this->assertNotNull($guide);
        $this->assertSame(DiveRateItemType::Guide, $guide->item_type);
        $this->assertSame('600000.00', $guide->price);
        $this->assertSame(1, DiveRateItem::query()->where('item_type', DiveRateItemType::Guide)->count());

        $motor = DiveRateItem::query()->where('code', 'DT-RENT-MOTOR')->first();
        $this->assertNotNull($motor);
        $this->assertSame(DiveRateItemType::DailyTripRental, $motor->item_type);
        $this->assertSame('200000.00', $motor->price);

        $camera = DiveRateItem::query()->where('code', 'DT-RENT-UNDERWATER-CAMERA')->first();
        $this->assertNotNull($camera);
        $this->assertSame(DiveRateItemType::DailyTripRental, $camera->item_type);
        $this->assertSame('350000.00', $camera->price);
    }

    public function test_new_rental_rates_import_idempotently(): void
    {
        $this->artisan('pratasaba:import-dive-price-list')->assertSuccessful();
        $countAfterFirst = DiveRateItem::query()->count();

        $this->artisan('pratasaba:import-dive-price-list')->assertSuccessful();

        $this->assertSame($countAfterFirst, DiveRateItem::query()->count());
        $this->assertSame('200000.00', DiveRateItem::query()->where('code', 'DT-RENT-MOTOR')->value('price'));
        $this->assertSame('350000.00', DiveRateItem::query()->where('code', 'DT-RENT-UNDERWATER-CAMERA')->value('price'));
        $this->assertSame(1, DiveRateItem::query()->where('code', 'DT-GUIDE')->count());
    }
}
