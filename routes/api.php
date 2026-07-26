<?php

use App\Http\Controllers\Ota\BookingWebhookController;
use App\Http\Controllers\Telegram\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', [WebhookController::class, 'handle'])
    ->name('telegram.webhook')
    ->middleware('throttle:telegram');

Route::post('/ota/bookings', [BookingWebhookController::class, 'store'])
    ->name('ota.bookings.store')
    ->middleware('throttle:ota');
