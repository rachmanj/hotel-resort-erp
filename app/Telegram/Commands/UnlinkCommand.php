<?php

namespace App\Telegram\Commands;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Telegram\TelegramConversationManager;
use App\Telegram\TelegramResponder;

class UnlinkCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private TelegramConversationManager $conversationManager,
    ) {
        parent::__construct($responder);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        $this->conversationManager->cancelFlowForUser($tgUser);

        $tgUser->update([
            'user_id' => null,
            'hotel_id' => null,
            'linked_at' => null,
        ]);

        $this->reply($tgUser, '🔓 Account unlinked. Use /link <code> to re-link.');
    }
}
