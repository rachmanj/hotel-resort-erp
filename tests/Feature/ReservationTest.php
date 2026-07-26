<?php

namespace Tests\Feature;

use App\Actions\Reservations\CreateReservationAction;
use App\Enums\RoomStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReservationTest extends TestCase
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
            'base_rate' => 500000,
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

        $this->user = User::factory()->create([
            'hotel_id' => null,
        ]);
        $this->user->assignRole('admin');
        $this->hotel->users()->attach($this->user->id);
    }

    public function test_reservations_index_requires_authentication(): void
    {
        $this->get('/reservations')->assertRedirect('/login');
    }

    public function test_admin_can_view_reservations_index(): void
    {
        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->get('/reservations')
            ->assertOk();
    }

    public function test_create_reservation_action_assigns_room_and_guest(): void
    {
        session(['current_hotel_id' => $this->hotel->id]);

        $reservation = app(CreateReservationAction::class)([
            'hotel_id' => $this->hotel->id,
            'arrival_date' => now()->addDay()->toDateString(),
            'departure_date' => now()->addDays(3)->toDateString(),
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'guest' => [
                'full_name' => 'John Guest',
                'phone' => '081234567890',
            ],
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'guest_id' => $reservation->guest_id,
            'hotel_id' => $this->hotel->id,
        ]);

        $this->assertDatabaseHas('guests', [
            'full_name' => 'John Guest',
            'phone' => '081234567890',
        ]);

        $this->assertDatabaseHas('reservation_rooms', [
            'reservation_id' => $reservation->id,
            'room_id' => $this->room->id,
            'status' => 'booked',
        ]);

        $this->assertMatchesRegularExpression('/^RES-\d{8}-\d{4}$/', $reservation->reservation_code);
    }

    public function test_overlapping_booking_is_rejected(): void
    {
        session(['current_hotel_id' => $this->hotel->id]);

        $action = app(CreateReservationAction::class);
        $arrival = now()->addDays(5)->toDateString();
        $departure = now()->addDays(7)->toDateString();

        $action([
            'hotel_id' => $this->hotel->id,
            'arrival_date' => $arrival,
            'departure_date' => $departure,
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'guest' => ['full_name' => 'First Guest', 'phone' => '081111111111'],
        ]);

        $this->expectException(RoomNotAvailableException::class);

        $action([
            'hotel_id' => $this->hotel->id,
            'arrival_date' => $arrival,
            'departure_date' => $departure,
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'guest' => ['full_name' => 'Second Guest', 'phone' => '082222222222'],
        ]);
    }

    public function test_find_or_create_guest_by_phone(): void
    {
        session(['current_hotel_id' => $this->hotel->id]);

        $existing = Guest::query()->create([
            'full_name' => 'Returning Guest',
            'phone' => '083333333333',
        ]);

        $reservation = app(CreateReservationAction::class)([
            'hotel_id' => $this->hotel->id,
            'arrival_date' => now()->addDays(10)->toDateString(),
            'departure_date' => now()->addDays(11)->toDateString(),
            'room_type_id' => $this->roomType->id,
            'guest' => [
                'full_name' => 'Different Name',
                'phone' => '083333333333',
            ],
        ]);

        $this->assertSame($existing->id, $reservation->guest_id);
    }
}
