<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Api;

class TelegramWebhookCommand extends Command
{
    protected $signature = 'telegram:webhook {action : set or remove} {url? : Webhook URL (required for set)}';

    protected $description = 'Register or remove the Telegram bot webhook';

    public function handle(): int
    {
        $token = config('telegram.bot_token');

        if (empty($token)) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in .env');

            return self::FAILURE;
        }

        $telegram = new Api($token);
        $action = $this->argument('action');

        if ($action === 'set') {
            $url = $this->argument('url');

            if (empty($url)) {
                $this->error('Webhook URL is required. Example: php artisan telegram:webhook set https://your-domain.com/api/telegram/webhook');

                return self::FAILURE;
            }

            $params = ['url' => $url];

            $secret = config('telegram.webhook_secret');

            if ($secret) {
                $params['secret_token'] = $secret;
            }

            $response = $telegram->setWebhook($params);
            $this->info('Webhook set: '.json_encode($response));

            return self::SUCCESS;
        }

        if ($action === 'remove') {
            $response = $telegram->removeWebhook();
            $this->info('Webhook removed: '.json_encode($response));

            return self::SUCCESS;
        }

        $this->error("Unknown action: {$action}. Use 'set' or 'remove'.");

        return self::FAILURE;
    }
}
