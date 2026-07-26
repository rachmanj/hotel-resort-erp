<?php

namespace App\Jobs;

use App\Models\TelegramUser;
use App\Telegram\TelegramCommandRouter;
use App\Telegram\TelegramConversationManager;
use App\Telegram\TelegramResponder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessTelegramUpdate implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $update
     */
    public function __construct(
        public array $update,
    ) {}

    public function handle(
        TelegramCommandRouter $router,
        TelegramConversationManager $conversationManager,
        TelegramResponder $responder,
    ): void {
        try {
            $this->process($router, $conversationManager, $responder);
        } catch (\Throwable $e) {
            Log::error('Telegram update processing failed', [
                'error' => $e->getMessage(),
                'update' => $this->update,
            ]);

            $chatId = $this->extractChatId();

            if ($chatId !== null) {
                $responder->sendMessage($chatId, '⚠️ Something went wrong. Please try again later.');
            }
        }
    }

    private function process(
        TelegramCommandRouter $router,
        TelegramConversationManager $conversationManager,
        TelegramResponder $responder,
    ): void {
        if (isset($this->update['callback_query'])) {
            $this->handleCallbackQuery($router, $conversationManager, $this->update['callback_query']);

            return;
        }

        $message = $this->update['message'] ?? $this->update['edited_message'] ?? null;

        if ($message === null) {
            return;
        }

        $chatId = (int) ($message['chat']['id'] ?? 0);
        $text = trim($message['text'] ?? '');

        if ($chatId === 0 || $text === '') {
            return;
        }

        $tgUser = $this->resolveTelegramUser($chatId, $message);

        if ($tgUser->hotel_id !== null) {
            session(['current_hotel_id' => $tgUser->hotel_id]);
        }

        $command = null;
        $args = [];

        if (str_starts_with($text, '/')) {
            $parts = explode(' ', $text, 2);
            $command = strtolower(explode('@', $parts[0])[0]);
            $args = isset($parts[1]) ? array_values(array_filter(explode(' ', trim($parts[1])))) : [];
        }

        $publicCommands = ['/start', '/link', '/help'];

        if (! $tgUser->isLinked() && ! in_array($command, $publicCommands, true)) {
            $responder->sendMessage(
                $chatId,
                'Please link your account first. Use /start for instructions.',
            );

            return;
        }

        $activeFlow = $conversationManager->getActiveFlow($tgUser);

        if ($activeFlow !== null && $command === null) {
            $router->routeFlowStep($tgUser, $activeFlow, $text);

            return;
        }

        if ($activeFlow !== null && $command !== null && ! in_array($command, $publicCommands, true)) {
            $conversationManager->cancelFlow($activeFlow);
        }

        if ($command !== null) {
            $router->route($command, $args, $tgUser, $activeFlow);
        }
    }

    /**
     * @param  array<string, mixed>  $callbackQuery
     */
    private function handleCallbackQuery(
        TelegramCommandRouter $router,
        TelegramConversationManager $conversationManager,
        array $callbackQuery,
    ): void {
        $responder = app(TelegramResponder::class);
        $callbackId = $callbackQuery['id'] ?? '';
        $data = $callbackQuery['data'] ?? '';
        $chatId = (int) ($callbackQuery['message']['chat']['id'] ?? 0);

        if ($chatId === 0 || $data === '') {
            return;
        }

        $responder->answerCallbackQuery($callbackId);

        $message = $callbackQuery['message'] ?? [];
        $tgUser = $this->resolveTelegramUser($chatId, $message);

        if ($tgUser->hotel_id !== null) {
            session(['current_hotel_id' => $tgUser->hotel_id]);
        }

        if (! $tgUser->isLinked()) {
            $responder->sendMessage($chatId, 'Please link your account first. Use /start for instructions.');

            return;
        }

        $activeFlow = $conversationManager->getActiveFlow($tgUser);
        $router->routeCallback($tgUser, $data, $activeFlow);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function resolveTelegramUser(int $chatId, array $message): TelegramUser
    {
        $username = $message['from']['username'] ?? null;

        $tgUser = TelegramUser::query()
            ->withoutGlobalScope('hotel')
            ->where('chat_id', $chatId)
            ->first();

        if ($tgUser === null) {
            return TelegramUser::query()->create([
                'chat_id' => $chatId,
                'telegram_username' => $username,
                'is_active' => true,
            ]);
        }

        if ($username !== null && $tgUser->telegram_username !== $username) {
            $tgUser->update(['telegram_username' => $username]);
        }

        return $tgUser;
    }

    private function extractChatId(): ?int
    {
        if (isset($this->update['callback_query']['message']['chat']['id'])) {
            return (int) $this->update['callback_query']['message']['chat']['id'];
        }

        if (isset($this->update['message']['chat']['id'])) {
            return (int) $this->update['message']['chat']['id'];
        }

        return null;
    }
}
