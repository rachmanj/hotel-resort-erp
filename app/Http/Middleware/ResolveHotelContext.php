<?php

namespace App\Http\Middleware;

use App\Models\Hotel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveHotelContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $accessibleHotels = $user->accessibleHotels()->get(['id', 'name', 'logo_path', 'currency']);
        $sessionHotelId = $request->session()->get('current_hotel_id');

        $currentHotel = null;

        if ($sessionHotelId !== null && $user->canAccessHotel((int) $sessionHotelId)) {
            $currentHotel = $accessibleHotels->firstWhere('id', (int) $sessionHotelId)
                ?? Hotel::query()->find($sessionHotelId);
        }

        if ($currentHotel === null && $accessibleHotels->isNotEmpty()) {
            $currentHotel = $accessibleHotels->first();
            $request->session()->put('current_hotel_id', $currentHotel->id);
        }

        if ($currentHotel !== null) {
            session(['current_hotel_id' => $currentHotel->id]);
        }

        $request->attributes->set('currentHotel', $currentHotel);
        $request->attributes->set('availableHotels', $accessibleHotels->count() > 1 ? $accessibleHotels : collect());

        return $next($request);
    }
}
