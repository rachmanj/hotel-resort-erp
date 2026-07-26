<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'bot_username' => env('TELEGRAM_BOT_USERNAME', 'HotelERP_bot'),
    'conversation_ttl_minutes' => 15,
];
