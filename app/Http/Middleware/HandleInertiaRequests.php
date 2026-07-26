<?php

namespace App\Http\Middleware;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->only(['id', 'name', 'email']),
                'roles' => $user?->getRoleNames()->values()->all(),
                'permissions' => $user?->getAllPermissions()->pluck('name')->values()->all(),
            ],
            'currentHotel' => fn () => $request->attributes->get('currentHotel')?->only(['id', 'name', 'logo_path', 'currency']),
            'availableHotels' => fn () => $request->attributes->get('availableHotels', collect())->map(
                fn ($hotel) => $hotel->only(['id', 'name', 'logo_path', 'currency'])
            )->values(),
            'currencies' => fn () => Cache::rememberForever('currencies.active', fn () => Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'symbol', 'name'])),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
