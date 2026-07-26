<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtaWebhookTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertAccepted()
            ->assertJsonPath('external_booking_id', 'OTA-12345')
            ->assertJsonPath('status', 'accepted');
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
            ->assertAccepted();
    }
}
