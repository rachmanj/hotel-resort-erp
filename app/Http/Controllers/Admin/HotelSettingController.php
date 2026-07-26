<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Hotel\UpdateHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateHotelSettingsRequest;
use App\Models\Hotel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotelSettingController extends Controller
{
    public function edit(Request $request): Response|RedirectResponse
    {
        /** @var Hotel|null $hotel */
        $hotel = $request->attributes->get('currentHotel');

        if ($hotel === null) {
            return redirect()->route('dashboard')->with('error', 'No hotel context selected.');
        }

        return Inertia::render('Admin/HotelSettings/Edit', [
            'hotel' => $hotel->only([
                'id',
                'name',
                'address',
                'logo_path',
                'currency',
                'timezone',
                'default_checkin_time',
                'default_checkout_time',
            ]),
        ]);
    }

    public function update(UpdateHotelSettingsRequest $request, UpdateHotel $updateHotel): RedirectResponse
    {
        /** @var Hotel|null $hotel */
        $hotel = $request->attributes->get('currentHotel');

        if ($hotel === null) {
            return redirect()->route('dashboard')->with('error', 'No hotel context selected.');
        }

        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('hotels/'.$hotel->id, 'public');
            $data['logo_path'] = $path;
        }

        unset($data['logo']);

        $updateHotel($hotel, $data);

        return back()->with('success', 'Hotel settings updated successfully.');
    }
}
