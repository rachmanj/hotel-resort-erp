<?php

namespace App\Http\Controllers;

use App\Actions\Reservations\CheckOutGuestAction;
use App\Enums\ReservationRoomStatus;
use App\Models\ReservationRoom;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class CheckOutController extends Controller
{
    public function store(ReservationRoom $reservationRoom, CheckOutGuestAction $checkOut): RedirectResponse
    {
        if ($reservationRoom->status !== ReservationRoomStatus::CheckedIn) {
            return back()->with('error', 'Room is not checked in.');
        }

        try {
            $result = $checkOut($reservationRoom, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $balance = number_format($result['balance'], 0, ',', '.');

        return back()->with('success', "Checkout complete. Folio balance: Rp{$balance}");
    }
}
