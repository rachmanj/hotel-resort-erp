<?php

namespace Tests\Feature;

use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\FolioType;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Models\Hotel;
use Database\Seeders\BillingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResplitInclusiveTaxCommandTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private Folio $folio;

    private Folio $otherFolio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(BillingDemoSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Pratasaba Resort',
            'code' => 'PSB',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $guest = Guest::query()->create(['full_name' => 'Legacy Guest']);

        $this->folio = Folio::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_no' => 'FOL-LEGACY-0001',
            'guest_id' => $guest->id,
            'type' => FolioType::Master->value,
            'status' => FolioStatus::Closed->value,
            'opened_at' => now()->subDays(3),
            'closed_at' => now()->subDay(),
        ]);

        $this->otherFolio = Folio::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_no' => 'FOL-LEGACY-0002',
            'guest_id' => $guest->id,
            'type' => FolioType::Master->value,
            'status' => FolioStatus::Closed->value,
            'opened_at' => now()->subDays(3),
            'closed_at' => now()->subDay(),
        ]);
    }

    public function test_dry_run_reports_the_split_without_writing_anything(): void
    {
        $item = $this->legacyItem($this->folio, FolioItemType::Room, 1_690_000, serviceCharge: 169_000, tax: 204_490);

        $this->artisan('billing:resplit-inclusive-tax', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->expectsOutputToContain('before: amount 1.690.000,00 · SC 169.000,00 · tax 204.490,00 · line total 2.063.490,00')
            ->expectsOutputToContain('after:  amount 1.690.000,00 · DPP 1.396.694,21 · SC 139.669,42 · PBJT 153.636,36 · line total 1.690.000,00')
            ->expectsOutputToContain('1 folio item(s) would be re-split.')
            ->assertSuccessful();

        $item->refresh();

        $this->assertFalse($item->is_tax_inclusive);
        $this->assertEquals(169_000, (float) $item->service_charge_amount);
        $this->assertEquals(204_490, (float) $item->tax_amount);
    }

    public function test_resplit_keeps_the_price_list_amount_and_populates_the_inclusive_split(): void
    {
        $room = $this->legacyItem($this->folio, FolioItemType::Room, 1_690_000, serviceCharge: 169_000, tax: 204_490);
        $fb = $this->legacyItem($this->folio, FolioItemType::Fb, 200_000, serviceCharge: 20_000, tax: 24_200);

        $this->artisan('billing:resplit-inclusive-tax')
            ->expectsOutputToContain('2 folio item(s) re-split as tax inclusive.')
            ->assertSuccessful();

        $room->refresh();
        $fb->refresh();

        $this->assertTrue($room->is_tax_inclusive);
        $this->assertEquals(1_690_000, (float) $room->amount);
        $this->assertEquals(1_690_000, (float) $room->unit_price);
        $this->assertEquals(139_669.42, (float) $room->service_charge_amount);
        $this->assertEquals(153_636.36, (float) $room->tax_amount);
        $this->assertEquals(1_690_000, $room->line_total);

        $this->assertTrue($fb->is_tax_inclusive);
        $this->assertEquals(200_000, (float) $fb->amount);
        $this->assertEquals(16_528.93, (float) $fb->service_charge_amount);
        $this->assertEquals(18_181.82, (float) $fb->tax_amount);
        $this->assertEquals(200_000, $fb->line_total);
    }

    public function test_command_is_idempotent(): void
    {
        $room = $this->legacyItem($this->folio, FolioItemType::Room, 1_690_000, serviceCharge: 169_000, tax: 204_490);

        $this->artisan('billing:resplit-inclusive-tax')->assertSuccessful();

        $afterFirstRun = $room->refresh()->only(['amount', 'service_charge_amount', 'tax_amount', 'is_tax_inclusive']);

        $this->artisan('billing:resplit-inclusive-tax')
            ->expectsOutputToContain('Nothing to re-split.')
            ->assertSuccessful();

        $this->assertSame($afterFirstRun, $room->refresh()->only(['amount', 'service_charge_amount', 'tax_amount', 'is_tax_inclusive']));
        $this->assertEquals(1_690_000, $room->line_total);
    }

    public function test_misc_items_are_left_alone(): void
    {
        $dive = $this->legacyItem($this->folio, FolioItemType::Misc, 1_200_000, serviceCharge: 0, tax: 0);

        $this->artisan('billing:resplit-inclusive-tax')
            ->expectsOutputToContain('Nothing to re-split.')
            ->assertSuccessful();

        $dive->refresh();

        $this->assertFalse($dive->is_tax_inclusive);
        $this->assertEquals(0, (float) $dive->service_charge_amount);
        $this->assertEquals(0, (float) $dive->tax_amount);
        $this->assertEquals(1_200_000, $dive->line_total);
    }

    public function test_folio_option_limits_the_rewrite_to_one_folio(): void
    {
        $target = $this->legacyItem($this->folio, FolioItemType::Room, 1_690_000, serviceCharge: 169_000, tax: 204_490);
        $untouched = $this->legacyItem($this->otherFolio, FolioItemType::Room, 1_690_000, serviceCharge: 169_000, tax: 204_490);

        $this->artisan('billing:resplit-inclusive-tax', ['--folio' => $this->folio->folio_no])
            ->expectsOutputToContain('1 folio item(s) re-split as tax inclusive.')
            ->assertSuccessful();

        $this->assertTrue($target->refresh()->is_tax_inclusive);
        $this->assertFalse($untouched->refresh()->is_tax_inclusive);
    }

    public function test_unknown_folio_option_fails(): void
    {
        $this->artisan('billing:resplit-inclusive-tax', ['--folio' => 'FOL-DOES-NOT-EXIST'])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }

    private function legacyItem(
        Folio $folio,
        FolioItemType $itemType,
        float $amount,
        float $serviceCharge,
        float $tax,
    ): FolioItem {
        return FolioItem::query()->create([
            'folio_id' => $folio->id,
            'item_type' => $itemType->value,
            'description' => "Legacy {$itemType->value} charge",
            'quantity' => 1,
            'unit_price' => $amount,
            'amount' => $amount,
            'service_charge_amount' => $serviceCharge,
            'tax_amount' => $tax,
            'is_tax_inclusive' => false,
            'posted_at' => now()->subDays(2),
        ]);
    }
}
