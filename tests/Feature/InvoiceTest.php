<?php

namespace Tests\Feature;

use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\FolioType;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\User;
use App\Services\FolioPostingService;
use Database\Seeders\AccountingDemoSeeder;
use Database\Seeders\BillingDemoSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InvoiceTest extends TestCase
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
            'name' => 'Test Hotel',
            'code' => 'TST',
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

        $guest = Guest::query()->create(['full_name' => 'Invoice Guest']);
        $this->folio = Folio::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_no' => 'FOL-INV-0001',
            'guest_id' => $guest->id,
            'type' => FolioType::Master->value,
            'status' => FolioStatus::Open->value,
            'opened_at' => now(),
        ]);
    }

    public function test_invoice_hides_tax_columns_when_folio_has_only_misc_charges(): void
    {
        app(FolioPostingService::class)->postCharge(
            folio: $this->folio,
            itemType: FolioItemType::Misc->value,
            description: 'Dive: Two Tank Dive',
            amount: 1_200_000,
            quantity: 1,
            postedBy: $this->user,
            applyTax: true,
        );

        $response = $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('folios.invoice', $this->folio));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Folios/Invoice')
            ->where('show_tax_columns', false)
        );
    }

    public function test_invoice_shows_tax_columns_when_folio_has_taxable_items(): void
    {
        app(FolioPostingService::class)->postCharge(
            folio: $this->folio,
            itemType: FolioItemType::Room->value,
            description: 'Room charge',
            amount: 1_000_000,
            quantity: 1,
            postedBy: $this->user,
            applyTax: true,
        );

        $response = $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('folios.invoice', $this->folio));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Folios/Invoice')
            ->where('show_tax_columns', true)
        );
    }

    public function test_invoice_blade_view_omits_sc_and_tax_headers_when_show_tax_columns_is_false(): void
    {
        $html = View::make('invoices.folio', $this->invoiceViewData(showTaxColumns: false))->render();

        $this->assertStringNotContainsString('>SC<', $html);
        $this->assertStringNotContainsString('>Tax<', $html);
    }

    public function test_invoice_blade_view_includes_sc_and_tax_headers_when_show_tax_columns_is_true(): void
    {
        $html = View::make('invoices.folio', $this->invoiceViewData(showTaxColumns: true))->render();

        $this->assertStringContainsString('>SC<', $html);
        $this->assertStringContainsString('>Tax<', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceViewData(bool $showTaxColumns): array
    {
        return [
            'folio' => [
                'folio_no' => 'FOL-INV-0001',
                'status' => 'open',
                'opened_at' => now()->format('d M Y H:i'),
                'closed_at' => null,
                'guest' => ['full_name' => 'Invoice Guest'],
                'reservation' => null,
                'company' => null,
                'items' => [
                    [
                        'description' => 'Dive: Two Tank Dive',
                        'quantity' => 1,
                        'unit_price' => 1_200_000,
                        'amount' => 1_200_000,
                        'tax_amount' => 0,
                        'service_charge_amount' => 0,
                        'line_total' => 1_200_000,
                    ],
                ],
            ],
            'balance' => 1_200_000,
            'charges_total' => 1_200_000,
            'show_tax_columns' => $showTaxColumns,
        ];
    }
}
