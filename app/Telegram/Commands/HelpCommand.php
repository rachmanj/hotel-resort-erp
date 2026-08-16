<?php

namespace App\Telegram\Commands;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Telegram\TelegramCommandRouter;

class HelpCommand extends BaseCommand
{
    public function authorize(TelegramUser $tgUser): bool
    {
        return true;
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        $commands = TelegramCommandRouter::commandDescriptions();
        $lines = ["📖 Available Commands\n"];

        foreach ($commands as $command => $info) {
            $handler = app($info['class']);

            if ($handler->authorize($tgUser)) {
                $lines[] = "{$command} · {$info['description']}";
            }
        }

        if (count($lines) === 1) {
            $lines[] = '/start · Welcome & link instructions';
            $lines[] = '/link <code> · Link your account';
        }

        $this->reply($tgUser, implode("\n", $lines));
    }
}
