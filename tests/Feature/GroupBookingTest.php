<?php

namespace Tests\Feature;

use App\Models\Floor;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GroupBookingTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

    private RoomType $roomType;

    private Room $room;

    private User $user;

    private Guest $guest;

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
            'status' => 'vacant_clean',
        ]);

        $this->user = User::factory()->create(['hotel_id' => null]);
        $this->user->assignRole('admin');
        $this->hotel->users()->attach($this->user->id);

        $this->guest = Guest::query()->create([
            'full_name' => 'Group PIC Guest',
            'phone' => '081234567890',
        ]);
    }

    public function test_store_type_a_group_creates_group_and_reservation(): void
    {
        $arrival = now()->addDay()->toDateString();
        $departure = now()->addDays(3)->toDateString();

        $response = $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('groups.store'), [
                'name' => 'Wedding Party Alpha',
                'group_type' => 'single_multi_room',
                'pic_guest_id' => $this->guest->id,
                'invoice_mode' => 'per_room',
                'deposit_amount' => 1000000,
                'special_requests' => 'Late check-in',
                'arrival_date' => $arrival,
                'departure_date' => $departure,
                'room_selections' => [
                    ['room_type_id' => $this->roomType->id],
                ],
                'reservation_data' => [
                    'guest_id' => $this->guest->id,
                    'adults' => 1,
                    'children' => 0,
                    'special_requests' => 'Late check-in',
                ],
            ]);

        $group = ReservationGroup::query()->first();
        $this->assertNotNull($group);

        $response->assertRedirect(route('groups.show', $group));

        $this->assertDatabaseHas('reservation_groups', [
            'id' => $group->id,
            'name' => 'Wedding Party Alpha',
            'group_type' => 'single_multi_room',
            'pic_guest_id' => $this->guest->id,
            'hotel_id' => $this->hotel->id,
        ]);

        $this->assertDatabaseHas('reservations', [
            'reservation_group_id' => $group->id,
            'guest_id' => $this->guest->id,
            'hotel_id' => $this->hotel->id,
        ]);

        $reservation = Reservation::query()->where('reservation_group_id', $group->id)->first();
        $this->assertNotNull($reservation);

        $this->assertDatabaseHas('reservation_rooms', [
            'reservation_id' => $reservation->id,
            'room_type_id' => $this->roomType->id,
        ]);
    }

    public function test_store_group_validation_failure_returns_422(): void
    {
        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('groups.create'))
            ->post(route('groups.store'), [
                'name' => '',
                'group_type' => 'single_multi_room',
                'pic_guest_id' => $this->guest->id,
                'arrival_date' => now()->addDay()->toDateString(),
                'departure_date' => now()->addDays(3)->toDateString(),
                'room_selections' => [
                    ['room_type_id' => $this->roomType->id],
                ],
            ])
            ->assertRedirect(route('groups.create'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('reservation_groups', 0);
    }

    public function test_store_group_rejects_non_numeric_deposit_amount(): void
    {
        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('groups.create'))
            ->post(route('groups.store'), [
                'name' => 'Deposit Format Test',
                'group_type' => 'single_multi_room',
                'pic_guest_id' => $this->guest->id,
                'deposit_amount' => '1,000,000',
                'arrival_date' => now()->addDay()->toDateString(),
                'departure_date' => now()->addDays(3)->toDateString(),
                'room_selections' => [
                    ['room_type_id' => $this->roomType->id],
                ],
                'reservation_data' => [
                    'guest_id' => $this->guest->id,
                    'adults' => 1,
                    'children' => 0,
                ],
            ])
            ->assertRedirect(route('groups.create'))
            ->assertSessionHasErrors('deposit_amount');

        $this->assertDatabaseCount('reservation_groups', 0);
    }
}
