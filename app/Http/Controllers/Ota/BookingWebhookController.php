<?php

namespace App\Http\Controllers\Ota;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ota\StoreOtaBookingRequest;
use Illuminate\Http\JsonResponse;

class BookingWebhookController extends Controller
{
    public function store(StoreOtaBookingRequest $request): JsonResponse
    {
        $secret = config('ota.webhook_secret');

        if ($secret && $request->header('X-OTA-Webhook-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'message' => 'OTA booking received (stub — reservation creation not yet implemented).',
            'external_booking_id' => $request->validated('external_booking_id'),
            'status' => 'accepted',
        ], 202);
    }
}
