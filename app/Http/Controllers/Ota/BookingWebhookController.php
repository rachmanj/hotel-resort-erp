<?php

namespace App\Http\Controllers\Ota;

use App\Actions\Reservations\CreateReservationAction;
use App\Enums\CreatedVia;
use App\Enums\ReservationSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ota\StoreOtaBookingRequest;
use App\Models\Agent;
use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\RoomType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class BookingWebhookController extends Controller
{
    public function __construct(
        private CreateReservationAction $createReservationAction,
    ) {}

    public function store(StoreOtaBookingRequest $request): JsonResponse
    {
        $secret = config('ota.webhook_secret');

        if ($secret && $request->header('X-OTA-Webhook-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validated();

        if ($validated['status'] === 'cancelled') {
            return response()->json([
                'message' => 'Cancellation acknowledged.',
                'external_booking_id' => $validated['external_booking_id'],
                'status' => 'cancelled',
            ]);
        }

        $hotel = Hotel::query()
            ->where('code', $validated['hotel_code'])
            ->where('is_active', true)
            ->first();

        if ($hotel === null) {
            return response()->json(['message' => 'Unknown hotel code.'], 422);
        }

        $existing = Reservation::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where('external_booking_id', $validated['external_booking_id'])
            ->first();

        if ($existing !== null) {
            return response()->json([
                'message' => 'Booking already exists.',
                'external_booking_id' => $validated['external_booking_id'],
                'reservation_code' => $existing->reservation_code,
                'status' => 'duplicate',
            ], 200);
        }

        $channelCode = $this->normalizeChannelCode($validated['channel']);

        $agent = Agent::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where('channel_code', $channelCode)
            ->where('is_active', true)
            ->first();

        if ($agent === null) {
            return response()->json(['message' => "Unknown OTA channel: {$validated['channel']}"], 422);
        }

        $roomType = RoomType::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where('code', $validated['room_type_code'])
            ->where('is_active', true)
            ->first();

        if ($roomType === null) {
            return response()->json(['message' => "Unknown room type code: {$validated['room_type_code']}"], 422);
        }

        session(['current_hotel_id' => $hotel->id]);

        $reservation = ($this->createReservationAction)([
            'hotel_id' => $hotel->id,
            'arrival_date' => $validated['arrival_date'],
            'departure_date' => $validated['departure_date'],
            'room_type_id' => $roomType->id,
            'adults' => $validated['adults'] ?? 1,
            'children' => $validated['children'] ?? 0,
            'special_requests' => $validated['special_requests'] ?? null,
            'source' => ReservationSource::Ota->value,
            'created_via' => CreatedVia::OtaWebhook->value,
            'agent_id' => $agent->id,
            'external_booking_id' => $validated['external_booking_id'],
            'guest' => $validated['guest'],
        ]);

        return response()->json([
            'message' => 'OTA booking created.',
            'external_booking_id' => $validated['external_booking_id'],
            'reservation_code' => $reservation->reservation_code,
            'status' => 'accepted',
        ], 201);
    }

    private function normalizeChannelCode(string $channel): string
    {
        $normalized = Str::lower(str_replace(['.', ' '], ['_', '_'], $channel));

        return match ($normalized) {
            'booking.com', 'booking_com' => 'booking_com',
            'traveloka' => 'traveloka',
            'agoda' => 'agoda',
            'expedia' => 'expedia',
            default => $normalized,
        };
    }
}
