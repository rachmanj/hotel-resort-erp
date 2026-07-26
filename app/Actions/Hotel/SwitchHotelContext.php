<?php

namespace App\Actions\Hotel;

use App\Models\Hotel;
use App\Models\User;

class SwitchHotelContext
{
    public function __invoke(User $user, int $hotelId): Hotel
    {
        if (! $user->canAccessHotel($hotelId)) {
            abort(403, 'You do not have access to this property.');
        }

        $hotel = Hotel::query()
            ->whereKey($hotelId)
            ->where('is_active', true)
            ->firstOrFail();

        session(['current_hotel_id' => $hotel->id]);

        return $hotel;
    }
}
