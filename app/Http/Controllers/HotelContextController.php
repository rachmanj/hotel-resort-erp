<?php

namespace App\Http\Controllers;

use App\Actions\Hotel\SwitchHotelContext;
use App\Http\Requests\SwitchHotelContextRequest;
use Illuminate\Http\JsonResponse;

class HotelContextController extends Controller
{
    public function switch(SwitchHotelContextRequest $request, SwitchHotelContext $switchHotelContext): JsonResponse
    {
        $hotel = $switchHotelContext($request->user(), (int) $request->validated('hotel_id'));

        return response()->json([
            'hotel' => $hotel->only(['id', 'name', 'logo_path', 'currency']),
        ]);
    }
}
