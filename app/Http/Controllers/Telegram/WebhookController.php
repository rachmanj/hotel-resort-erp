<?php

namespace App\Http\Controllers\Telegram;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessTelegramUpdate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = config('telegram.webhook_secret');

        if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            return response('Unauthorized', 401);
        }

        ProcessTelegramUpdate::dispatch($request->all());

        return response('OK', 200);
    }
}
