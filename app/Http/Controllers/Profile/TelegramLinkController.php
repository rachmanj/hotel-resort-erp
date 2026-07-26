<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\TelegramUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TelegramLinkController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        $telegramUser = TelegramUser::query()
            ->withoutGlobalScope('hotel')
            ->where('user_id', $user->id)
            ->first();

        return Inertia::render('Profile/TelegramLink', [
            'botUsername' => config('telegram.bot_username'),
            'linkCode' => $telegramUser?->link_code,
            'linkCodeExpiresAt' => $telegramUser?->link_code_expires_at?->toIso8601String(),
            'isLinked' => $telegramUser?->isLinked() ?? false,
            'linkedAt' => $telegramUser?->linked_at?->toIso8601String(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $user = $request->user();

        $code = strtoupper(Str::random(6));

        TelegramUser::query()
            ->withoutGlobalScope('hotel')
            ->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'link_code' => $code,
                    'link_code_expires_at' => now()->addMinutes(10),
                    'is_active' => true,
                ],
            );

        return back()->with('success', 'Link code generated. It expires in 10 minutes.');
    }
}
