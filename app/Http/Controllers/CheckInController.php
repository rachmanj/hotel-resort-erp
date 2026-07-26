<?php

namespace App\Http\Controllers;

use App\Actions\Reservations\CheckInGuestAction;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class CheckInController extends Controller
{
    public function store(Reservation $reservation, CheckInGuestAction $checkIn): RedirectResponse
    {
        if ($reservation->status !== ReservationStatus::Confirmed) {
            return back()->with('error', 'Only confirmed reservations can be checked in.');
        }

        try {
            $checkIn($reservation, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Guest checked in successfully. Folio opened.');
    }
}
