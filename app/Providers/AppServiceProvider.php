<?php

namespace App\Providers;

use App\Models\HousekeepingLog;
use App\Observers\HousekeepingLogObserver;
use App\Telegram\Notifications\TelegramChannel;
use App\WhatsApp\Notifications\WhatsAppChannel;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        HousekeepingLog::observe(HousekeepingLogObserver::class);

        Notification::extend('telegram', fn () => app(TelegramChannel::class));

        Notification::extend('whatsapp', fn () => app(WhatsAppChannel::class));
    }
}
