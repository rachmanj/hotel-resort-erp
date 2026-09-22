<?php

namespace Tests\Feature;

use App\Actions\Reservations\CreateReservationAction;
use App\Enums\ProformaInvoiceStatus;
use App\Enums\RoomStatus;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\ProformaInvoice;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProformaInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $roomType;

    private Room $firstRoom;

    private Room $secondRoom;

    private User $marketingUser;

    private User $financeUser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-15 09:00:00');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Pratasaba Resort',
            'code' => 'PRT',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $this->roomType = RoomType::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Seroja',
            'code' => 'SRJ-T',
            'max_occupancy' => 4,
            'base_rate' => 1_500_000,
            'is_active' => true,
        ]);

        $floor = Floor::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Floor 1',
            'level' => 1,
        ]);

        $this->firstRoom = Room::query()->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'floor_id' => $floor->id,
            'number' => '101',
            'status' => RoomStatus::VacantClean->value,
        ]);

        $this->secondRoom = Room::query()->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'floor_id' => $floor->id,
            'number' => '102',
            'status' => RoomStatus::VacantClean->value,
        ]);

        $this->marketingUser = User::factory()->create(['hotel_id' => null, 'name' => 'Marketing Staff']);
        $this->marketingUser->assignRole('front_office');
        $this->hotel->users()->attach($this->marketingUser->id);

        $this->financeUser = User::factory()->create(['hotel_id' => null, 'name' => 'Finance Staff']);
        $this->financeUser->assignRole('finance');
        $this->hotel->users()->attach($this->financeUser->id);

        session(['current_hotel_id' => $this->hotel->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reservation_creation_issues_draft_proforma_invoice_with_room_lines(): void
    {
        $reservation = $this->createReservationWithTwoRooms();

        $invoice = ProformaInvoice::query()->where('reservation_id', $reservation->id)->firstOrFail();

        $this->assertSame('072/PI/PRATA/VIII/2026', $invoice->number);
        $this->assertSame(ProformaInvoiceStatus::Draft, $invoice->status);
        $this->assertSame(1, $invoice->revision);
        $this->assertNull($invoice->issued_at);
        $this->assertSame('Marketing Staff', $invoice->prepared_by);
        $this->assertEquals(9_000_000, (float) $invoice->total);
        $this->assertEquals(9_000_000, (float) $invoice->subtotal);

        $lines = $invoice->lines;
        $this->assertCount(1, $lines);
        $this->assertSame('Seroja', $lines[0]->description);
        $this->assertSame('*Room Include Breakfast 2 pax', $lines[0]->note);
        $this->assertSame(2, $lines[0]->quantity);
        $this->assertSame(3, $lines[0]->nights);
        $this->assertEquals(1_500_000, (float) $lines[0]->unit_price);

        // Price is the nightly rate for one room, one night; Amount is Price x Qty x Ns.
        $this->assertEquals(1_500_000, $lines[0]->stayPrice());
        $this->assertEquals(9_000_000, (float) $lines[0]->amount);
    }

    public function test_draft_proforma_invoice_is_regenerated_when_rate_changes(): void
    {
        $reservation = $this->createReservationWithTwoRooms();

        $reservation->reservationRooms()->first()->update(['nightly_rate' => 2_000_000]);

        $invoices = ProformaInvoice::query()->where('reservation_id', $reservation->id)->get();

        $this->assertCount(1, $invoices);
        $this->assertSame(1, $invoices[0]->revision);
        $this->assertEquals(10_500_000, (float) $invoices[0]->total);
        $this->assertCount(2, $invoices[0]->lines);
    }

    public function test_marketing_can_view_proforma_invoice_but_cannot_release_it(): void
    {
        $reservation = $this->createReservationWithTwoRooms();

        $this->actingAs($this->marketingUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get("/reservations/{$reservation->id}/proforma")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reservations/Proforma')
                ->where('canRelease', false)
                ->where('proforma.number', '072/PI/PRATA/VIII/2026')
                ->has('proforma.lines', 1)
                ->etc()
            );

        $this->actingAs($this->marketingUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/proforma/release")
            ->assertForbidden();

        $this->assertDatabaseHas('proforma_invoices', [
            'reservation_id' => $reservation->id,
            'status' => ProformaInvoiceStatus::Draft->value,
        ]);
    }

    public function test_finance_can_release_proforma_invoice(): void
    {
        $reservation = $this->createReservationWithTwoRooms();

        $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/proforma/release")
            ->assertRedirect()
            ->assertSessionHas('success');

        $invoice = ProformaInvoice::query()->where('reservation_id', $reservation->id)->firstOrFail();

        $this->assertSame(ProformaInvoiceStatus::Released, $invoice->status);
        $this->assertSame($this->financeUser->id, $invoice->released_by);
        $this->assertNotNull($invoice->released_at);
        $this->assertNotNull($invoice->issued_at);
    }

    public function test_change_after_release_creates_next_revision_and_keeps_released_document(): void
    {
        $reservation = $this->createReservationWithTwoRooms();

        $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/proforma/release")
            ->assertRedirect();

        $released = ProformaInvoice::query()->where('reservation_id', $reservation->id)->firstOrFail();

        $reservation->reservationRooms()->first()->update(['nightly_rate' => 2_000_000]);

        $released->refresh()->load('lines');

        $this->assertSame(ProformaInvoiceStatus::Released, $released->status);
        $this->assertSame('072/PI/PRATA/VIII/2026', $released->number);
        $this->assertEquals(9_000_000, (float) $released->total);
        $this->assertCount(1, $released->lines);

        $revisions = ProformaInvoice::query()
            ->where('reservation_id', $reservation->id)
            ->orderBy('revision')
            ->get();

        $this->assertCount(2, $revisions);
        $this->assertSame(2, $revisions[1]->revision);
        $this->assertSame('073/PI/PRATA/VIII/2026', $revisions[1]->number);
        $this->assertSame(ProformaInvoiceStatus::Draft, $revisions[1]->status);
        $this->assertEquals(10_500_000, (float) $revisions[1]->total);
    }

    public function test_proforma_invoice_pdf_download_succeeds(): void
    {
        $reservation = $this->createReservationWithTwoRooms();

        $response = $this->actingAs($this->marketingUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get("/reservations/{$reservation->id}/proforma/download");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function createReservationWithTwoRooms(): Reservation
    {
        $guest = Guest::query()->create([
            'full_name' => 'Proforma Guest',
            'phone' => '081234567890',
        ]);

        return app(CreateReservationAction::class)([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $guest->id,
            'arrival_date' => '2026-08-20',
            'departure_date' => '2026-08-23',
            'room_selections' => [
                ['room_type_id' => $this->roomType->id, 'room_id' => $this->firstRoom->id],
                ['room_type_id' => $this->roomType->id, 'room_id' => $this->secondRoom->id],
            ],
            'adults' => 2,
            'created_by' => $this->marketingUser->id,
            'created_via' => 'web',
        ]);
    }
}
