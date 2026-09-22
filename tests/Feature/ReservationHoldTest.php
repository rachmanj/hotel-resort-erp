<?php

namespace Tests\Feature;

use App\Actions\Reservations\CreateReservationAction;
use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReservationHoldTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $roomType;

    private Room $room;

    private Room $secondRoom;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Hold Hotel',
            'code' => 'HLD',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $this->roomType = RoomType::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe',
            'code' => 'DLX-H',
            'max_occupancy' => 2,
            'base_rate' => 1000000,
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

        $this->secondRoom = Room::query()->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'floor_id' => $floor->id,
            'number' => '102',
            'status' => RoomStatus::VacantClean->value,
        ]);

        $this->user = User::factory()->create(['hotel_id' => null]);
        $this->user->assignRole('admin');
        $this->hotel->users()->attach($this->user->id);

        session(['current_hotel_id' => $this->hotel->id]);

        (new ChartOfAccountsSeeder)->forHotel($this->hotel);
    }

    public function test_new_reservation_starts_tentative_with_a_hold_limit(): void
    {
        config(['reservations.hold_days' => 3]);

        $reservation = $this->createReservation();

        $this->assertSame(ReservationStatus::Tentative, $reservation->status);
        $this->assertNotNull($reservation->hold_expires_at);
        $this->assertSame(
            now()->addDays(3)->toDateString(),
            $reservation->hold_expires_at->toDateString(),
        );
    }

    public function test_reservation_created_with_an_explicit_status_keeps_it_and_has_no_hold(): void
    {
        $reservation = $this->createReservation(['status' => ReservationStatus::Confirmed->value]);

        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertNull($reservation->hold_expires_at);
    }

    public function test_staff_can_manually_confirm_a_tentative_reservation(): void
    {
        $reservation = $this->createReservation();

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/confirm")
            ->assertRedirect()
            ->assertSessionHas('success', 'Reservation confirmed successfully.');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertNull($reservation->hold_expires_at);

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => (new Reservation)->getMorphClass(),
            'subject_id' => $reservation->id,
            'event' => 'confirmed',
        ]);
    }

    public function test_confirming_an_already_confirmed_reservation_is_refused(): void
    {
        $reservation = $this->createReservation();

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/confirm")
            ->assertRedirect();

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/confirm")
            ->assertRedirect()
            ->assertSessionHas('error', "Only tentative reservations can be confirmed, {$reservation->reservation_code} is Confirmed.");

        $this->assertSame(ReservationStatus::Confirmed, $reservation->refresh()->status);
    }

    public function test_confirm_requires_the_manage_permission(): void
    {
        $reservation = $this->createReservation();

        $housekeeper = User::factory()->create(['hotel_id' => null]);
        $housekeeper->assignRole('housekeeping');
        $this->hotel->users()->attach($housekeeper->id);

        $this->actingAs($housekeeper)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/confirm")
            ->assertForbidden();

        $this->assertSame(ReservationStatus::Tentative, $reservation->refresh()->status);
    }

    public function test_expire_holds_command_cancels_only_overdue_tentative_reservations(): void
    {
        $overdue = $this->createReservation();
        $overdue->update(['hold_expires_at' => now()->subDay()]);

        $stillHeld = $this->createReservation([
            'room_id' => $this->secondRoom->id,
            'arrival_date' => now()->addDays(20)->toDateString(),
            'departure_date' => now()->addDays(21)->toDateString(),
        ]);

        $confirmed = $this->createReservation([
            'status' => ReservationStatus::Confirmed->value,
            'arrival_date' => now()->addDays(30)->toDateString(),
            'departure_date' => now()->addDays(31)->toDateString(),
        ]);

        $withoutHold = $this->createReservation([
            'arrival_date' => now()->addDays(40)->toDateString(),
            'departure_date' => now()->addDays(41)->toDateString(),
        ]);
        $withoutHold->update(['hold_expires_at' => null]);

        $this->artisan('reservations:expire-holds')
            ->expectsOutputToContain($overdue->reservation_code)
            ->assertSuccessful();

        $overdue->refresh();
        $this->assertSame(ReservationStatus::Cancelled, $overdue->status);
        $this->assertStringContainsString('Hold expired', (string) $overdue->cancelled_reason);
        $this->assertSame(
            ReservationRoomStatus::Cancelled,
            $overdue->reservationRooms()->firstOrFail()->status,
        );

        $this->assertSame(ReservationStatus::Tentative, $stillHeld->refresh()->status);
        $this->assertSame(ReservationStatus::Confirmed, $confirmed->refresh()->status);
        $this->assertSame(ReservationStatus::Tentative, $withoutHold->refresh()->status);
    }

    public function test_expire_holds_command_is_idempotent(): void
    {
        $overdue = $this->createReservation();
        $overdue->update(['hold_expires_at' => now()->subDay()]);

        $this->artisan('reservations:expire-holds')->assertSuccessful();
        $cancelledAt = $overdue->refresh()->updated_at;

        $this->travel(1)->minutes();

        $this->artisan('reservations:expire-holds')
            ->expectsOutput('No expired reservation holds.')
            ->assertSuccessful();

        $this->assertSame(ReservationStatus::Cancelled, $overdue->refresh()->status);
        $this->assertEquals($cancelledAt, $overdue->updated_at);
    }

    public function test_check_in_is_refused_while_the_booking_is_tentative(): void
    {
        $reservation = $this->createReservation(['arrival_date' => now()->toDateString()]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/checkin")
            ->assertRedirect()
            ->assertSessionHas('error', 'This booking is still tentative. Confirm the reservation before checking the guest in.');

        $this->assertSame(ReservationStatus::Tentative, $reservation->refresh()->status);
        $this->assertDatabaseMissing('folios', ['reservation_id' => $reservation->id]);
    }

    public function test_confirmed_reservation_can_still_be_checked_in(): void
    {
        $reservation = $this->createReservation(['arrival_date' => now()->toDateString()]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/confirm")
            ->assertRedirect();

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/checkin")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(ReservationStatus::CheckedIn, $reservation->refresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createReservation(array $overrides = []): Reservation
    {
        $guest = Guest::query()->create(['full_name' => 'Hold Test Guest']);

        return app(CreateReservationAction::class)([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $guest->id,
            'arrival_date' => now()->addDays(10)->toDateString(),
            'departure_date' => now()->addDays(11)->toDateString(),
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'adults' => 1,
            'created_by' => $this->user->id,
            'created_via' => 'web',
            ...$overrides,
        ]);
    }
}
