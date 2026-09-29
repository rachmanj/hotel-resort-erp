<?php

namespace App\Http\Controllers;

use App\Actions\Reservations\CheckInGuestAction;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CheckInController extends Controller
{
    public function store(Request $request, Reservation $reservation, CheckInGuestAction $checkIn): RedirectResponse
    {
        if ($reservation->status === ReservationStatus::Tentative) {
            return back()->with('error', 'This booking is still tentative. Confirm the reservation before checking the guest in.');
        }

        if ($reservation->status !== ReservationStatus::Confirmed) {
            return back()->with('error', 'Only confirmed reservations can be checked in.');
        }

        $validated = $request->validate([
            'is_marketing_non_agent' => ['sometimes', 'boolean'],
        ]);

        $checkInOptions = array_key_exists('is_marketing_non_agent', $validated)
            ? ['is_marketing_non_agent' => (bool) $validated['is_marketing_non_agent']]
            : null;

        try {
            $checkIn($reservation, $request->user(), null, $checkInOptions);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Guest checked in successfully. Folio opened.');
    }
}
