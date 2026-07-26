<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('kitchen.{hotelId}', function ($user, int $hotelId) {
    return $user->can('fb.view') && session('current_hotel_id') === $hotelId;
});
