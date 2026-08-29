<?php

namespace Tests\Feature;

use App\Actions\Reservations\CreateReservationAction;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Models\ActivityLog;
use App\Models\Floor;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReservationWhatsAppTest extends TestCase
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

        config([
            'whatsapp.base_url' => 'http://openwa.test',
            'whatsapp.api_key' => 'test-key',
            'whatsapp.session_id' => 'test-session',
        ]);

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

        $this->user = User::factory()->create(['hotel_id' => null]);
        $this->user->assignRole('admin');
        $this->hotel->users()->attach($this->user->id);
    }

    public function test_send_whatsapp_requires_permission(): void
    {
        $reservation = $this->createReservation();

        $user = User::factory()->create(['hotel_id' => null]);
        $user->assignRole('housekeeping');
        $this->hotel->users()->attach($user->id);

        $this->actingAs($user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->post(route('reservations.send-whatsapp', $reservation))
            ->assertForbidden();
    }

    public function test_send_whatsapp_rejects_guest_without_phone(): void
    {
        Http::fake();

        $reservation = $this->createReservation(null);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('reservations.show', $reservation))
            ->post(route('reservations.send-whatsapp', $reservation))
            ->assertRedirect(route('reservations.show', $reservation))
            ->assertSessionHasErrors('phone');

        Http::assertNothingSent();
    }

    public function test_send_whatsapp_rejects_status_other_than_confirmed_or_cancelled(): void
    {
        Http::fake();

        $reservation = $this->createReservation();
        $reservation->update(['status' => ReservationStatus::Tentative->value]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('reservations.show', $reservation))
            ->post(route('reservations.send-whatsapp', $reservation))
            ->assertRedirect(route('reservations.show', $reservation))
            ->assertSessionHasErrors('status');

        Http::assertNothingSent();
    }

    public function test_send_whatsapp_sends_confirmation_for_confirmed_reservation(): void
    {
        Http::fake(['*' => Http::response(['messageId' => 'test-message-id'])]);

        $reservation = $this->createReservation();

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('reservations.show', $reservation))
            ->post(route('reservations.send-whatsapp', $reservation))
            ->assertRedirect(route('reservations.show', $reservation))
            ->assertSessionHas('success', 'WhatsApp confirmation sent successfully.');

        Http::assertSent(
            fn (Request $request) => $request->url() === 'http://openwa.test/api/sessions/test-session/messages/send-text'
                && data_get($request->data(), 'chatId') === '6281234567890@c.us'
                && str_contains(data_get($request->data(), 'text'), 'confirmed')
        );

        $log = ActivityLog::query()
            ->where('subject_type', (new Reservation)->getMorphClass())
            ->where('subject_id', $reservation->id)
            ->where('event', 'whatsapp_sent')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('WhatsApp confirmation sent', $log->properties['description']);
    }

    public function test_send_whatsapp_sends_cancellation_for_cancelled_reservation(): void
    {
        Http::fake(['*' => Http::response(['messageId' => 'test-message-id'])]);

        $reservation = $this->createReservation();
        $reservation->update(['status' => ReservationStatus::Cancelled->value]);

        $this->actingAs($this->user)
            ->withSession(['current_hotel_id' => $this->hotel->id])
            ->from(route('reservations.show', $reservation))
            ->post(route('reservations.send-whatsapp', $reservation))
            ->assertRedirect(route('reservations.show', $reservation))
            ->assertSessionHas('success', 'WhatsApp cancellation sent successfully.');

        Http::assertSent(
            fn (Request $request) => str_contains(data_get($request->data(), 'text'), '**cancelled**')
        );

        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => (new Reservation)->getMorphClass(),
            'subject_id' => $reservation->id,
            'event' => 'whatsapp_sent',
        ]);
    }

    private function createReservation(?string $phone = '081234567890'): Reservation
    {
        $guest = Guest::query()->create([
            'full_name' => 'WA Test Guest',
            'phone' => $phone,
        ]);

        return app(CreateReservationAction::class)([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $guest->id,
            'arrival_date' => now()->addDay()->toDateString(),
            'departure_date' => now()->addDays(3)->toDateString(),
            'room_type_id' => $this->roomType->id,
            'room_id' => $this->room->id,
            'adults' => 1,
            'children' => 0,
            'created_by' => $this->user->id,
            'created_via' => 'web',
        ]);
    }
}
