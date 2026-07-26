<?php

namespace App\Telegram\Contracts;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;

interface TelegramCommand
{
    public function authorize(TelegramUser $tgUser): bool;

    /**
     * @param  list<string>  $args
     */
    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void;
}
