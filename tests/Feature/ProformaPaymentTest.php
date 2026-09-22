<?php

namespace Tests\Feature;

use App\Actions\Reservations\CreateReservationAction;
use App\Enums\ProformaPaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\ProformaInvoice;
use App\Models\ProformaPayment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProformaPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $roomType;

    private Room $room;

    private User $marketingUser;

    private User $financeUser;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-15 09:00:00');

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

        $this->room = Room::query()->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'floor_id' => $floor->id,
            'number' => '101',
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

    public function test_recording_a_payment_updates_outstanding_but_issues_no_receipt(): void
    {
        Storage::fake('public');

        $reservation = $this->createReservation();

        $this->actingAs($this->marketingUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/proforma/payments", [
                'amount' => 2_000_000,
                'method' => 'bank_transfer',
                'received_from' => 'PT Nusantara Jaya',
                'reference_no' => 'TRF-9912',
                'paid_at' => '2026-06-15',
                'proof' => UploadedFile::fake()->image('slip.jpg'),
                'notes' => 'Down payment 50%',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment = ProformaPayment::query()->firstOrFail();
        $invoice = ProformaInvoice::query()->where('reservation_id', $reservation->id)->firstOrFail();

        $this->assertSame(ProformaPaymentStatus::Recorded, $payment->status);
        $this->assertNull($payment->receipt_number);
        $this->assertNull($payment->receipt_issued_at);
        $this->assertNull($payment->verified_by);
        $this->assertSame($this->marketingUser->id, $payment->recorded_by);
        Storage::disk('public')->assertExists($payment->proof_path);

        // Money is only counted once finance has seen it in the bank account.
        $this->assertEquals(0, (float) $invoice->received_total);
        $this->assertEquals(4_500_000, (float) $invoice->outstanding_total);
        $this->assertSame(ReservationStatus::Tentative, $reservation->refresh()->status);

        // Down payments taken at booking time never touch the folio level payments table.
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_verifying_a_payment_issues_a_receipt_and_confirms_the_reservation(): void
    {
        $reservation = $this->createReservation();
        $payment = $this->recordPayment($reservation, 2_000_000);

        $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/proforma-payments/{$payment->id}/verify")
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment->refresh();
        $invoice = ProformaInvoice::query()->where('reservation_id', $reservation->id)->firstOrFail();

        $this->assertSame(ProformaPaymentStatus::Verified, $payment->status);
        $this->assertSame('PR-001/PI/PRATA/VI/2026', $payment->receipt_number);
        $this->assertSame(1, $payment->receipt_sequence);
        $this->assertNotNull($payment->receipt_issued_at);
        $this->assertSame($this->financeUser->id, $payment->verified_by);

        $this->assertEquals(2_000_000, (float) $invoice->received_total);
        $this->assertEquals(2_500_000, (float) $invoice->outstanding_total);
        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
        $this->assertNull($reservation->hold_expires_at);
    }

    public function test_second_receipt_against_the_same_invoice_is_suffixed(): void
    {
        $reservation = $this->createReservation();
        $first = $this->recordPayment($reservation, 2_000_000);
        $second = $this->recordPayment($reservation, 2_500_000);

        $this->actingAs($this->financeUser)->withSession(['current_hotel_id' => $this->hotel->id]);

        $this->post("/proforma-payments/{$first->id}/verify")->assertRedirect();
        $this->post("/proforma-payments/{$second->id}/verify")->assertRedirect();

        $this->assertSame('PR-001/PI/PRATA/VI/2026', $first->refresh()->receipt_number);
        $this->assertSame('PR-001/PI/PRATA/VI/2026-2', $second->refresh()->receipt_number);

        $invoice = ProformaInvoice::query()->where('reservation_id', $reservation->id)->firstOrFail();

        $this->assertEquals(4_500_000, (float) $invoice->received_total);
        $this->assertEquals(0, (float) $invoice->outstanding_total);
    }

    public function test_verifying_an_already_verified_payment_is_refused(): void
    {
        $reservation = $this->createReservation();
        $payment = $this->recordPayment($reservation, 2_000_000);

        $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/proforma-payments/{$payment->id}/verify")
            ->assertRedirect();

        $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/proforma-payments/{$payment->id}/verify")
            ->assertRedirect()
            ->assertSessionHas('error', 'Payment already verified, receipt PR-001/PI/PRATA/VI/2026 was issued.');

        $invoice = ProformaInvoice::query()->where('reservation_id', $reservation->id)->firstOrFail();

        $this->assertEquals(2_000_000, (float) $invoice->received_total);
        $this->assertSame(1, ProformaPayment::query()->whereNotNull('receipt_number')->count());
    }

    public function test_user_without_verify_permission_cannot_verify(): void
    {
        $reservation = $this->createReservation();
        $payment = $this->recordPayment($reservation, 2_000_000);

        $this->actingAs($this->marketingUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/proforma-payments/{$payment->id}/verify")
            ->assertForbidden();

        $this->assertSame(ProformaPaymentStatus::Recorded, $payment->refresh()->status);
        $this->assertNull($payment->receipt_number);
        $this->assertSame(ReservationStatus::Tentative, $reservation->refresh()->status);
    }

    public function test_proforma_page_exposes_payments_and_permission_flags(): void
    {
        $reservation = $this->createReservation();
        $this->recordPayment($reservation, 2_000_000);

        $this->actingAs($this->marketingUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get("/reservations/{$reservation->id}/proforma")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reservations/Proforma')
                ->where('canRecordPayment', true)
                ->where('canVerifyPayment', false)
                ->where('proforma.outstanding_total', 4_500_000)
                ->has('payments', 1)
                ->where('payments.0.status', 'recorded')
                ->etc()
            );
    }

    public function test_receipt_pdf_download_succeeds_for_verified_payment(): void
    {
        $reservation = $this->createReservation();
        $payment = $this->recordPayment($reservation, 310_000_000);

        $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/proforma-payments/{$payment->id}/verify")
            ->assertRedirect();

        $response = $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get("/proforma-payments/{$payment->id}/receipt");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_receipt_is_not_available_before_verification(): void
    {
        $reservation = $this->createReservation();
        $payment = $this->recordPayment($reservation, 2_000_000);

        $this->actingAs($this->financeUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get("/proforma-payments/{$payment->id}/receipt")
            ->assertNotFound();
    }

    private function recordPayment(Reservation $reservation, float $amount): ProformaPayment
    {
        $this->actingAs($this->marketingUser)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/proforma/payments", [
                'amount' => $amount,
                'method' => 'bank_transfer',
                'received_from' => 'PT Nusantara Jaya',
                'reference_no' => 'TRF-9912',
                'paid_at' => '2026-06-15',
            ])
            ->assertRedirect();

        return ProformaPayment::query()->latest('id')->firstOrFail();
    }

    private function createReservation(): Reservation
    {
        $guest = Guest::query()->create([
            'full_name' => 'Proforma Guest',
            'phone' => '081234567890',
        ]);

        // A booking held by marketing stays tentative until the down payment clears.
        return app(CreateReservationAction::class)([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $guest->id,
            'arrival_date' => '2026-06-20',
            'departure_date' => '2026-06-23',
            'room_selections' => [
                ['room_type_id' => $this->roomType->id, 'room_id' => $this->room->id],
            ],
            'adults' => 2,
            'created_by' => $this->marketingUser->id,
            'created_via' => 'web',
        ]);
    }
}
