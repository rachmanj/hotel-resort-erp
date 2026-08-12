<?php

namespace Tests\Feature;

use App\Enums\AgentType;
use App\Enums\CommissionBasis;
use App\Enums\RoomStatus;
use App\Models\Agent;
use App\Models\Floor;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OtaWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Hotel $hotel;

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

        Agent::query()->create([
            'hotel_id' => $this->hotel->id,
            'agent_type' => AgentType::Ota->value,
            'name' => 'Booking.com',
            'code' => 'BCOM',
            'channel_code' => 'booking_com',
            'commission_percent' => 15,
            'commission_basis' => CommissionBasis::NetRoom->value,
            'is_active' => true,
        ]);

        $roomType = RoomType::query()->create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe',
            'code' => 'DLX',
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
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'hotel_code' => 'TST',
            'external_booking_id' => 'OTA-12345',
            'channel' => 'booking.com',
            'status' => 'new',
            'guest' => [
                'full_name' => 'OTA Guest',
                'phone' => '081234567890',
            ],
            'arrival_date' => now()->addDay()->toDateString(),
            'departure_date' => now()->addDays(3)->toDateString(),
            'room_type_code' => 'DLX',
            'adults' => 2,
        ];
    }

    public function test_ota_webhook_accepts_valid_payload(): void
    {
        config(['ota.webhook_secret' => null]);

        $this->postJson('/api/ota/bookings', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('external_booking_id', 'OTA-12345')
            ->assertJsonPath('status', 'accepted');

        $this->assertDatabaseHas('reservations', [
            'external_booking_id' => 'OTA-12345',
            'hotel_id' => $this->hotel->id,
        ]);
    }

    public function test_ota_webhook_rejects_invalid_payload(): void
    {
        config(['ota.webhook_secret' => null]);

        $this->postJson('/api/ota/bookings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hotel_code', 'external_booking_id', 'channel', 'status', 'guest', 'arrival_date', 'departure_date', 'room_type_code']);
    }

    public function test_ota_webhook_requires_secret_when_configured(): void
    {
        config(['ota.webhook_secret' => 'test-secret']);

        $this->postJson('/api/ota/bookings', $this->validPayload())
            ->assertUnauthorized();

        $this->postJson('/api/ota/bookings', $this->validPayload(), [
            'X-OTA-Webhook-Secret' => 'test-secret',
        ])
            ->assertCreated();
    }

    public function test_ota_webhook_rejects_unknown_channel(): void
    {
        config(['ota.webhook_secret' => null]);

        $payload = $this->validPayload();
        $payload['channel'] = 'unknown_ota';

        $this->postJson('/api/ota/bookings', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Unknown OTA channel: unknown_ota');
    }

    public function test_ota_webhook_handles_duplicate_booking(): void
    {
        config(['ota.webhook_secret' => null]);

        $this->postJson('/api/ota/bookings', $this->validPayload())->assertCreated();

        $this->postJson('/api/ota/bookings', $this->validPayload())
            ->assertOk()
            ->assertJsonPath('status', 'duplicate');

        $this->assertEquals(1, Reservation::query()->where('external_booking_id', 'OTA-12345')->count());
    }
}
