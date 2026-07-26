<?php

use App\Http\Controllers\Telegram\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/telegram/webhook', [WebhookController::class, 'handle'])
    ->name('telegram.webhook')
    ->middleware('throttle:telegram');
