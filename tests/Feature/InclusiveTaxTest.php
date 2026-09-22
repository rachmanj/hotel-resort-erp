<?php

namespace Tests\Feature;

use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\FolioType;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\GeneralLedger;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\TaxRule;
use App\Models\User;
use App\Services\FolioPostingService;
use App\Services\Reports\PbjtReport;
use App\Services\TaxCalculator;
use Database\Seeders\AccountingDemoSeeder;
use Database\Seeders\BillingDemoSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InclusiveTaxTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $user;

    private Folio $folio;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDemoSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Pratasaba Resort',
            'code' => 'PSB',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        (new ChartOfAccountsSeeder)->forHotel($this->hotel);
        (new AccountingDemoSeeder)->run();

        $this->user = User::factory()->create(['hotel_id' => null]);
        $this->user->assignRole('admin');
        $this->hotel->users()->attach($this->user->id);

        session(['current_hotel_id' => $this->hotel->id]);

        $guest = Guest::query()->create(['full_name' => 'Inclusive Guest']);
        $this->folio = Folio::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_no' => 'FOL-INC-0001',
            'guest_id' => $guest->id,
            'type' => FolioType::Master->value,
            'status' => FolioStatus::Open->value,
            'opened_at' => now(),
        ]);
    }

    public function test_active_tax_rules_are_service_charge_10_and_pbjt_10(): void
    {
        $this->assertSame(2, TaxRule::query()->count());
        $this->assertDatabaseHas('tax_rules', [
            'code' => 'service_charge',
            'rate_percent' => 10.00,
            'is_compounding' => false,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('tax_rules', [
            'code' => 'pbjt',
            'rate_percent' => 10.00,
            'is_compounding' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseMissing('tax_rules', ['code' => 'ppn']);
    }

    public function test_room_charge_splits_the_price_list_amount_without_adding_anything_on_top(): void
    {
        $item = $this->postCharge(FolioItemType::Room, 'Room 101 · 1 night(s)', 1_690_000);

        $split = app(TaxCalculator::class)->extractInclusive(1_690_000, 'room');

        $this->assertEquals(1_396_694.21, $split['dpp']);
        $this->assertEquals(139_669.42, $split['service_charge']);
        $this->assertEquals(153_636.36, $split['tax']);

        $this->assertTrue($item->is_tax_inclusive);
        $this->assertEquals(1_690_000, (float) $item->unit_price);
        $this->assertEquals(1_690_000, (float) $item->amount);
        $this->assertEquals(139_669.42, (float) $item->service_charge_amount);
        $this->assertEquals(153_636.36, (float) $item->tax_amount);
        $this->assertEquals(1_690_000, $item->line_total);
    }

    public function test_restaurant_charge_splits_the_price_list_amount_without_adding_anything_on_top(): void
    {
        $item = $this->postCharge(FolioItemType::Fb, 'Restaurant order #1', 200_000);

        $split = app(TaxCalculator::class)->extractInclusive(200_000, 'fb');

        $this->assertEquals(165_289.26, $split['dpp']);
        $this->assertEquals(16_528.93, $split['service_charge']);
        $this->assertEquals(18_181.82, $split['tax']);

        $this->assertTrue($item->is_tax_inclusive);
        $this->assertEquals(200_000, (float) $item->amount);
        $this->assertEquals(16_528.93, (float) $item->service_charge_amount);
        $this->assertEquals(18_181.82, (float) $item->tax_amount);
        $this->assertEquals(200_000, $item->line_total);
    }

    public function test_dive_charge_carries_no_service_charge_and_no_tax(): void
    {
        $item = $this->postCharge(FolioItemType::Misc, 'Dive: Two Tank Dive', 1_200_000);

        $this->assertFalse($item->is_tax_inclusive);
        $this->assertEquals(1_200_000, (float) $item->amount);
        $this->assertEquals(0, (float) $item->service_charge_amount);
        $this->assertEquals(0, (float) $item->tax_amount);
        $this->assertEquals(1_200_000, $item->line_total);
    }

    public function test_folio_totals_never_add_the_split_back_on_top(): void
    {
        $this->postCharge(FolioItemType::Room, 'Room 101 · 1 night(s)', 1_690_000);
        $this->postCharge(FolioItemType::Fb, 'Restaurant order #1', 200_000);
        $this->postCharge(FolioItemType::Misc, 'Dive: Two Tank Dive', 1_200_000);

        $folioPostingService = app(FolioPostingService::class);

        $this->assertEquals(3_090_000, $folioPostingService->getChargesTotal($this->folio));
        $this->assertEquals(3_090_000, $folioPostingService->getBalance($this->folio));
    }

    public function test_gl_entry_for_an_inclusive_room_charge_balances_on_the_price_list_amount(): void
    {
        $item = $this->postCharge(FolioItemType::Room, 'Room 101 · 1 night(s)', 1_690_000);

        $rows = GeneralLedger::query()
            ->where('source_type', 'folio_item')
            ->where('source_id', $item->id)
            ->get();

        $this->assertEquals(1_690_000, round((float) $rows->sum('debit'), 2));
        $this->assertEquals(1_690_000, round((float) $rows->sum('credit'), 2));

        $taxLine = $rows->first(fn ($row) => str_starts_with($row->description, 'PBJT:'));
        $this->assertNotNull($taxLine);
        $this->assertEquals(153_636.36, round((float) $taxLine->credit, 2));

        $revenueLine = $rows->first(fn ($row) => $row->description === 'Room 101 · 1 night(s)' && (float) $row->credit > 0);
        $this->assertEquals(1_396_694.22, round((float) $revenueLine->credit, 2));
    }

    public function test_pbjt_tax_report_sums_the_split_for_room_and_fb_revenue(): void
    {
        $this->postCharge(FolioItemType::Room, 'Room 101 · 1 night(s)', 1_690_000);
        $this->postCharge(FolioItemType::Fb, 'Restaurant order #1', 200_000);
        $this->postCharge(FolioItemType::Misc, 'Dive: Two Tank Dive', 1_200_000);

        $report = app(PbjtReport::class)->generate($this->hotel->id, now()->format('Y-m'));

        $this->assertCount(2, $report['by_item_type']);

        $room = collect($report['by_item_type'])->firstWhere('item_type', 'room');
        $this->assertEquals(1_690_000, $room['gross']);
        $this->assertEquals(139_669.42, $room['service_charge']);
        $this->assertEquals(153_636.36, $room['tax']);
        $this->assertEquals(1_396_694.22, $room['dpp']);

        $fb = collect($report['by_item_type'])->firstWhere('item_type', 'fb');
        $this->assertEquals(200_000, $fb['gross']);
        $this->assertEquals(16_528.93, $fb['service_charge']);
        $this->assertEquals(18_181.82, $fb['tax']);

        $this->assertEquals(1_890_000, $report['totals']['gross']);
        $this->assertEquals(156_198.35, $report['totals']['service_charge']);
        $this->assertEquals(171_818.18, $report['totals']['tax']);
        $this->assertEquals(
            $report['totals']['gross'],
            round($report['totals']['dpp'] + $report['totals']['service_charge'] + $report['totals']['tax'], 2),
        );
    }

    public function test_guest_invoice_prints_the_flat_price_with_no_service_charge_or_tax_columns(): void
    {
        $this->postCharge(FolioItemType::Room, 'Room 101 · 1 night(s)', 1_690_000);

        $response = $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('folios.guest-invoice', $this->folio));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Folios/GuestInvoice')
            ->missing('show_tax_columns')
            ->where('invoice.total', 1_690_000)
            ->where('invoice.lines.0.line_total', 1_690_000)
            ->missing('invoice.lines.0.tax_amount')
            ->missing('invoice.lines.0.service_charge_amount')
            ->etc()
        );

        $html = View::make('invoices.guest', $this->guestInvoiceViewData())->render();

        $this->assertStringNotContainsString('>SC<', $html);
        $this->assertStringNotContainsString('>Tax<', $html);
        $this->assertStringContainsString('1.690.000', $html);
    }

    public function test_folio_invoice_prints_the_flat_price_with_no_service_charge_or_tax_columns(): void
    {
        $this->postCharge(FolioItemType::Room, 'Room 101 · 1 night(s)', 1_690_000);

        $response = $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('folios.invoice', $this->folio));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Folios/Invoice')
            ->missing('show_tax_columns')
            ->where('charges_total', 1_690_000)
            ->missing('folio.items.0.tax_amount')
            ->missing('folio.items.0.service_charge_amount')
            ->etc()
        );

        $html = View::make('invoices.folio', $this->folioInvoiceViewData())->render();

        $this->assertStringNotContainsString('>SC<', $html);
        $this->assertStringNotContainsString('>Tax<', $html);
    }

    private function postCharge(FolioItemType $itemType, string $description, float $amount): FolioItem
    {
        return app(FolioPostingService::class)->postCharge(
            folio: $this->folio,
            itemType: $itemType->value,
            description: $description,
            amount: $amount,
            quantity: 1,
            postedBy: $this->user,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function guestInvoiceViewData(): array
    {
        return [
            'company' => ['name' => 'Pratasaba Resort', 'title' => 'INVOICE'],
            'folio' => ['id' => $this->folio->id, 'folio_no' => $this->folio->folio_no],
            'customer' => ['id' => 1, 'name' => 'Inclusive Guest', 'contact' => null],
            'invoice' => [
                'number' => '2609001',
                'revision' => 1,
                'date' => now()->format('d M Y'),
                'status' => 'draft',
                'prepared_by' => 'Cashier',
                'approved_by' => null,
                'total' => 1_690_000,
                'lines' => [
                    [
                        'description' => 'Room 101 · 1 night(s)',
                        'quantity' => 1,
                        'nights' => 1,
                        'unit_price' => 1_690_000,
                        'amount' => 1_690_000,
                        'line_total' => 1_690_000,
                    ],
                ],
            ],
            'terms' => [
                'title' => config('invoice.terms_title'),
                'bank_accounts' => config('invoice.bank_accounts'),
                'items' => config('invoice.terms'),
            ],
            'signatures' => config('invoice.signatures'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function folioInvoiceViewData(): array
    {
        return [
            'folio' => [
                'folio_no' => $this->folio->folio_no,
                'status' => 'open',
                'opened_at' => now()->format('d M Y H:i'),
                'closed_at' => null,
                'guest' => ['full_name' => 'Inclusive Guest'],
                'reservation' => null,
                'company' => null,
                'items' => [
                    [
                        'description' => 'Room 101 · 1 night(s)',
                        'quantity' => 1,
                        'unit_price' => 1_690_000,
                        'amount' => 1_690_000,
                        'line_total' => 1_690_000,
                    ],
                ],
            ],
            'balance' => 1_690_000,
            'charges_total' => 1_690_000,
        ];
    }
}
