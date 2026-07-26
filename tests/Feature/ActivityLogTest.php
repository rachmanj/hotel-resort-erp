<?php

namespace Tests\Feature;

use App\Actions\Reservations\CreateReservationAction;
use App\Enums\RoomStatus;
use App\Models\ActivityLog;
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

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

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

        $roomType = RoomType::query()->create([
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

        Room::query()->create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $roomType->id,
            'floor_id' => $floor->id,
            'number' => '101',
            'status' => RoomStatus::VacantClean->value,
        ]);

        $this->user = User::factory()->create(['hotel_id' => null]);
        $this->user->assignRole('admin');
        $this->hotel->users()->attach($this->user->id);

        session(['current_hotel_id' => $this->hotel->id]);
    }

    public function test_reservation_creation_is_logged(): void
    {
        $this->actingAs($this->user);

        $guest = Guest::query()->create(['full_name' => 'Logged Guest']);

        $reservation = app(CreateReservationAction::class)([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $guest->id,
            'arrival_date' => now()->addDay()->toDateString(),
            'departure_date' => now()->addDays(2)->toDateString(),
            'room_type_id' => RoomType::query()->first()->id,
            'adults' => 1,
            'created_by' => $this->user->id,
            'created_via' => 'web',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'hotel_id' => $this->hotel->id,
            'user_id' => $this->user->id,
            'subject_type' => $reservation->getMorphClass(),
            'subject_id' => $reservation->id,
            'event' => 'created',
        ]);

        $log = ActivityLog::query()->where('subject_id', $reservation->id)->first();
        $this->assertNotNull($log);
        $this->assertArrayHasKey('attributes', $log->properties);
    }
}
