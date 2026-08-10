<?php

namespace App\Telegram\Commands;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;

class StartCommand extends BaseCommand
{
    public function authorize(TelegramUser $tgUser): bool
    {
        return true;
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! empty($args) && ! $tgUser->isLinked()) {
            $linkCommand = app(LinkCommand::class);
            $linkCommand->handle($args, $tgUser, $state);

            return;
        }

        $this->reply(
            $tgUser,
            "Welcome to Pratasaba ERP System! 🏨\n\n".
            "To get started, link your account with /link &lt;code&gt;CODE&lt;/code&gt;.\n".
            'Get your linking code from your profile page on the web app.',
        );
    }
}
