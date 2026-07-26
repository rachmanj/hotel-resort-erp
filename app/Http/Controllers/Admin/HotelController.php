<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Hotel\CreateHotel;
use App\Actions\Hotel\SyncHotelUserAccess;
use App\Actions\Hotel\UpdateHotel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHotelRequest;
use App\Http\Requests\Admin\UpdateHotelRequest;
use App\Http\Requests\Admin\UpdateHotelUserAccessRequest;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HotelController extends Controller
{
    public function index(Request $request): Response
    {
        $hotels = Hotel::query()
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Hotels/Index', [
            'hotels' => $hotels,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Hotels/Create');
    }

    public function store(StoreHotelRequest $request, CreateHotel $createHotel): RedirectResponse
    {
        $createHotel($request->validated());

        return redirect()->route('admin.hotels.index')->with('success', 'Hotel created successfully.');
    }

    public function edit(Hotel $hotel): Response
    {
        return Inertia::render('Admin/Hotels/Edit', [
            'hotel' => $hotel,
        ]);
    }

    public function update(UpdateHotelRequest $request, Hotel $hotel, UpdateHotel $updateHotel): RedirectResponse
    {
        $updateHotel($hotel, $request->validated());

        return redirect()->route('admin.hotels.index')->with('success', 'Hotel updated successfully.');
    }

    public function userAccess(Hotel $hotel): Response
    {
        return Inertia::render('Admin/Hotels/UserAccess', [
            'hotel' => $hotel->only(['id', 'name', 'code']),
            'assignedUserIds' => $hotel->users()->pluck('users.id'),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function updateUserAccess(UpdateHotelUserAccessRequest $request, Hotel $hotel, SyncHotelUserAccess $syncHotelUserAccess): RedirectResponse
    {
        $syncHotelUserAccess($hotel, $request->validated('user_ids'));

        return back()->with('success', 'User access updated successfully.');
    }
}
