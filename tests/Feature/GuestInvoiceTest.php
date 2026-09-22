<?php

namespace Tests\Feature;

use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\FolioType;
use App\Enums\GuestInvoiceStatus;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\GuestInvoice;
use App\Models\Hotel;
use App\Models\User;
use App\Services\FolioPostingService;
use Database\Seeders\AccountingDemoSeeder;
use Database\Seeders\BillingDemoSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GuestInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private User $frontOfficeUser;

    private User $financeUser;

    private Folio $folio;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-15 09:00:00');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(BillingDemoSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Pratasaba Resort',
            'code' => 'PRT',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        (new ChartOfAccountsSeeder)->forHotel($this->hotel);
        (new AccountingDemoSeeder)->run();

        $this->frontOfficeUser = User::factory()->create(['hotel_id' => null, 'name' => 'Front Office Staff']);
        $this->frontOfficeUser->assignRole('front_office');
        $this->hotel->users()->attach($this->frontOfficeUser->id);

        $this->financeUser = User::factory()->create(['hotel_id' => null, 'name' => 'Finance Staff']);
        $this->financeUser->assignRole('finance');
        $this->hotel->users()->attach($this->financeUser->id);

        session(['current_hotel_id' => $this->hotel->id]);

        $guest = Guest::query()->create(['full_name' => 'Invoice Guest', 'phone' => '081234567890']);

        $this->folio = Folio::query()->create([
            'hotel_id' => $this->hotel->id,
            'folio_no' => 'FOL-GI-0001',
            'guest_id' => $guest->id,
            'type' => FolioType::Master->value,
            'status' => FolioStatus::Open->value,
            'opened_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_draft_invoice_is_created_from_folio_items_and_follows_later_folio_changes(): void
    {
        $this->postMiscCharge();

        $invoice = $this->currentInvoice();

        $this->assertSame('2607001', $invoice->number);
        $this->assertSame(GuestInvoiceStatus::Draft, $invoice->status);
        $this->assertSame(1, $invoice->revision);
        $this->assertNull($invoice->issued_at);
        $this->assertNull($invoice->approved_by);
        $this->assertSame($this->frontOfficeUser->id, $invoice->prepared_by);
        $this->assertEquals(1_200_000, (float) $invoice->total);

        $lines = $invoice->lines;
        $this->assertCount(1, $lines);
        $this->assertSame('Dive: Two Tank Dive', $lines[0]->description);
        $this->assertEquals(1, (float) $lines[0]->quantity);
        $this->assertNull($lines[0]->nights);
        $this->assertEquals(1_200_000, (float) $lines[0]->unit_price);
        $this->assertEquals(1_200_000, (float) $lines[0]->line_total);

        $this->postRoomCharge();

        $invoice = $this->currentInvoice();

        $this->assertCount(1, GuestInvoice::query()->where('folio_id', $this->folio->id)->get());
        $this->assertSame('2607001', $invoice->number);
        $this->assertSame(1, $invoice->revision);

        $lines = $invoice->lines;
        $this->assertCount(2, $lines);

        // Room charges are posted with quantity holding the nights, which prints in the Ns column.
        $this->assertEquals(1, (float) $lines[1]->quantity);
        $this->assertSame(3, $lines[1]->nights);
        $this->assertEquals(1_000_000, (float) $lines[1]->unit_price);
        $this->assertEquals(3_000_000, (float) $lines[1]->amount);
        $this->assertGreaterThan(0, (float) $lines[1]->tax_amount);

        $this->assertEquals(
            1_200_000 + $lines[1]->line_total,
            (float) $invoice->total,
        );
    }

    public function test_user_without_invoice_release_permission_cannot_release_the_invoice(): void
    {
        $this->postMiscCharge();

        $this->actingAs($this->frontOfficeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('folios.guest-invoice', $this->folio))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Folios/GuestInvoice')
                ->where('canRelease', false)
                ->where('invoice.number', '2607001')
                ->has('invoice.lines', 1)
                ->etc()
            );

        $this->actingAs($this->frontOfficeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('folios.guest-invoice.release', $this->folio))
            ->assertForbidden();

        $this->assertDatabaseHas('guest_invoices', [
            'folio_id' => $this->folio->id,
            'status' => GuestInvoiceStatus::Draft->value,
        ]);
    }

    public function test_release_stamps_the_document_and_a_later_folio_change_creates_the_next_revision(): void
    {
        $this->postMiscCharge();

        $this->releaseAsFinance()
            ->assertRedirect()
            ->assertSessionHas('success');

        $released = $this->currentInvoice();

        $this->assertSame(GuestInvoiceStatus::Released, $released->status);
        $this->assertSame($this->financeUser->id, $released->released_by);
        $this->assertSame($this->financeUser->id, $released->approved_by);
        $this->assertNotNull($released->issued_at);
        $this->assertNotNull($released->released_at);

        $this->postRoomCharge();

        $released->refresh()->load('lines');

        $this->assertSame(GuestInvoiceStatus::Released, $released->status);
        $this->assertSame('2607001', $released->number);
        $this->assertCount(1, $released->lines);
        $this->assertEquals(1_200_000, (float) $released->total);

        $revisions = GuestInvoice::query()
            ->where('folio_id', $this->folio->id)
            ->orderBy('revision')
            ->get();

        $this->assertCount(2, $revisions);
        $this->assertSame(2, $revisions[1]->revision);
        $this->assertSame('2607002', $revisions[1]->number);
        $this->assertSame(GuestInvoiceStatus::Draft, $revisions[1]->status);
        $this->assertCount(2, $revisions[1]->lines);
    }

    public function test_releasing_an_already_released_invoice_is_refused(): void
    {
        $this->postMiscCharge();

        $this->releaseAsFinance()->assertSessionHas('success');

        $this->releaseAsFinance()
            ->assertRedirect()
            ->assertSessionHas('error', 'Invoice 2607001 has already been released and cannot be released again.');

        $this->assertCount(1, GuestInvoice::query()->where('folio_id', $this->folio->id)->get());
    }

    public function test_guest_invoice_pdf_download_succeeds(): void
    {
        $this->postMiscCharge();

        $response = $this->actingAs($this->frontOfficeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('folios.guest-invoice.download', $this->folio));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_invoice_omits_service_charge_and_tax_columns_for_non_taxable_charges(): void
    {
        $this->postMiscCharge();

        $this->actingAs($this->frontOfficeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('folios.guest-invoice', $this->folio))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Folios/GuestInvoice')
                ->missing('show_tax_columns')
                ->etc()
            );

        $html = View::make('invoices.guest', $this->documentViewData())->render();

        $this->assertStringNotContainsString('>SC<', $html);
        $this->assertStringNotContainsString('>Tax<', $html);
    }

    public function test_invoice_omits_service_charge_and_tax_columns_for_tax_inclusive_charges_too(): void
    {
        $this->postRoomCharge();

        $this->actingAs($this->frontOfficeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get(route('folios.guest-invoice', $this->folio))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Folios/GuestInvoice')
                ->missing('show_tax_columns')
                ->missing('invoice.lines.0.tax_amount')
                ->missing('invoice.lines.0.service_charge_amount')
                ->where('invoice.total', 3_000_000)
                ->etc()
            );
    }

    private function postMiscCharge(): void
    {
        app(FolioPostingService::class)->postCharge(
            folio: $this->folio,
            itemType: FolioItemType::Misc->value,
            description: 'Dive: Two Tank Dive',
            amount: 1_200_000,
            quantity: 1,
            postedBy: $this->frontOfficeUser,
        );
    }

    private function postRoomCharge(): void
    {
        app(FolioPostingService::class)->postCharge(
            folio: $this->folio,
            itemType: FolioItemType::Room->value,
            description: 'Room 101 · 3 night(s)',
            amount: 1_000_000,
            quantity: 3,
            postedBy: $this->frontOfficeUser,
        );
    }

    private function releaseAsFinance(): TestResponse
    {
        return $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('folios.guest-invoice.release', $this->folio));
    }

    private function currentInvoice(): GuestInvoice
    {
        return GuestInvoice::query()
            ->where('folio_id', $this->folio->id)
            ->orderByDesc('revision')
            ->with('lines')
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function documentViewData(): array
    {
        return [
            'company' => ['name' => 'PRATASABA RESORT', 'title' => 'INVOICE'],
            'folio' => ['id' => $this->folio->id, 'folio_no' => $this->folio->folio_no],
            'customer' => ['id' => 12, 'name' => 'Invoice Guest', 'contact' => '081234567890'],
            'invoice' => [
                'id' => 1,
                'number' => '2607001',
                'status' => 'released',
                'revision' => 1,
                'date' => '15 Jul 2026',
                'prepared_by' => 'Front Office Staff',
                'approved_by' => 'Finance Staff',
                'subtotal' => 1_200_000,
                'total' => 1_200_000,
                'lines' => [
                    [
                        'description' => 'Dive: Two Tank Dive',
                        'quantity' => 1,
                        'nights' => null,
                        'unit_price' => 1_200_000,
                        'amount' => 1_200_000,
                        'line_total' => 1_200_000,
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
}
