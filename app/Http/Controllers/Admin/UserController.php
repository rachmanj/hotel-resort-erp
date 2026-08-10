<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with(['homeHotel:id,name,code', 'roles:id,name'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'hotel_id' => $user->hotel_id,
                'roles' => $user->roles->pluck('name')->all(),
                'roles_label' => $user->roles->pluck('name')->join(', '),
                'home_hotel' => $user->homeHotel?->only(['id', 'name', 'code']),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'hotels' => Hotel::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Create', [
            'hotels' => Hotel::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['roles']);
        $user = User::query()->create($data);
        $user->syncRoles($request->input('roles', []));

        return back()->with('success', 'User created successfully.');
    }

    public function edit(User $user): Response
    {
        $user->load(['homeHotel:id,name,code', 'roles:id,name']);

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'hotel_id' => $user->hotel_id,
                'roles' => $user->roles->pluck('name')->all(),
            ],
            'hotels' => Hotel::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->safe()->except(['roles', 'password']);

        if ($request->filled('password')) {
            $data['password'] = $request->validated('password');
        }

        $user->update($data);
        $user->syncRoles($request->input('roles', []));

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->hasRole('admin')) {
            $adminCount = User::role('admin')->count();
            if ($adminCount <= 1) {
                return back()->with('error', 'Cannot delete the last admin user.');
            }
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }
}
