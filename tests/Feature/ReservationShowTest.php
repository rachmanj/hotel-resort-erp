<?php

namespace Tests\Feature;

use App\Actions\Reservations\CreateReservationAction;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\AccountingPeriod;
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

class ReservationShowTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $roomType;

    private Room $room;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolePermissionSeeder::class);

        $this->hotel = Hotel::query()->create([
            'name' => 'Test Hotel',
            'code' => 'TST',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $this->roomType = RoomType::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe',
            'code' => 'DLX-T',
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

        $this->user = User::factory()->create(['hotel_id' => null]);
        $this->user->assignRole('admin');
        $this->hotel->users()->attach($this->user->id);

        session(['current_hotel_id' => $this->hotel->id]);

        (new ChartOfAccountsSeeder)->forHotel($this->hotel);
    }

    public function test_reservation_show_page_renders_inertia_component(): void
    {
        $reservation = $this->createReservation();

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get("/reservations/{$reservation->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Reservations/Show')
                ->has('reservation', fn ($reservationProp) => $reservationProp
                    ->where('id', $reservation->id)
                    ->where('children_count', 0)
                    ->has('reservation_rooms', 1)
                    ->has('guest')
                    ->etc()
                )
                ->where('canCheckIn', true)
            );
    }

    public function test_check_in_auto_creates_missing_accounting_period(): void
    {
        AccountingPeriod::query()->withoutGlobalScope('hotel')->delete();

        $reservation = $this->createReservation();

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/checkin")
            ->assertRedirect()
            ->assertSessionHas('success');

        $reservation->refresh();
        $this->assertEquals(ReservationStatus::CheckedIn, $reservation->status);

        $this->assertDatabaseHas('accounting_periods', [
            'hotel_id' => $this->hotel->id,
            'name' => now()->format('Y-m'),
            'status' => 'open',
        ]);
    }

    public function test_check_in_rejects_non_confirmed_reservation(): void
    {
        $reservation = $this->createReservation();
        $reservation->update(['status' => ReservationStatus::CheckedIn->value]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post("/reservations/{$reservation->id}/checkin")
            ->assertRedirect()
            ->assertSessionHas('error', 'Only confirmed reservations can be checked in.');
    }

    private function createReservation(): Reservation
    {
        $guest = Guest::query()->create(['full_name' => 'Show Page Guest']);

        return app(CreateReservationAction::class)([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $guest->id,
            'arrival_date' => now()->toDateString(),
            'departure_date' => now()->addDay()->toDateString(),
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'adults' => 1,
            'children' => 0,
            'created_by' => $this->user->id,
            'created_via' => 'web',
        ]);
    }
}
