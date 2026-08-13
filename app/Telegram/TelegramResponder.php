<?php

namespace App\Telegram;

use Telegram\Bot\Api;
use Telegram\Bot\Keyboard\Keyboard;

class TelegramResponder
{
    public function __construct(
        private ?Api $telegram = null,
    ) {
        $this->telegram ??= new Api(config('telegram.bot_token'));
    }

    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void
    {
        if (empty(config('telegram.bot_token'))) {
            return;
        }

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }

        $this->telegram->sendMessage($params);
    }

    public function sendInlineKeyboard(int $chatId, string $text, array $buttons): void
    {
        $this->sendMessage($chatId, $text, Keyboard::make([
            'inline_keyboard' => $buttons,
        ])->toArray());
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        if (empty(config('telegram.bot_token'))) {
            return;
        }

        $params = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $params['text'] = $text;
        }

        $this->telegram->answerCallbackQuery($params);
    }

    public function editMessage(int $chatId, int $messageId, string $text, ?array $replyMarkup = null): void
    {
        if (empty(config('telegram.bot_token'))) {
            return;
        }

        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }

        $this->telegram->editMessageText($params);
    }
}
