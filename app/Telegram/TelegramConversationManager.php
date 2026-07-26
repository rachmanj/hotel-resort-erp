<?php

namespace App\Telegram;

use App\Models\TelegramConversationState;
use App\Models\TelegramUser;

class TelegramConversationManager
{
    public function startFlow(TelegramUser $tgUser, string $flow, array $initialPayload = []): TelegramConversationState
    {
        $this->cancelFlowForUser($tgUser);

        return TelegramConversationState::query()->create([
            'telegram_user_id' => $tgUser->id,
            'flow' => $flow,
            'step' => 'start',
            'payload' => $initialPayload,
            'expires_at' => now()->addMinutes(config('telegram.conversation_ttl_minutes', 15)),
        ]);
    }

    public function advanceStep(TelegramConversationState $state, string $nextStep, array $data = []): TelegramConversationState
    {
        $payload = array_merge($state->payload ?? [], $data);

        $state->update([
            'step' => $nextStep,
            'payload' => $payload,
            'expires_at' => now()->addMinutes(config('telegram.conversation_ttl_minutes', 15)),
        ]);

        return $state->fresh();
    }

    public function completeFlow(TelegramConversationState $state): void
    {
        $state->delete();
    }

    public function cancelFlow(TelegramConversationState $state): void
    {
        $state->delete();
    }

    public function getActiveFlow(TelegramUser $tgUser): ?TelegramConversationState
    {
        $state = TelegramConversationState::query()
            ->where('telegram_user_id', $tgUser->id)
            ->latest('id')
            ->first();

        if ($state === null) {
            return null;
        }

        if ($state->isExpired()) {
            $state->delete();

            return null;
        }

        return $state;
    }

    public function cancelFlowForUser(TelegramUser $tgUser): void
    {
        TelegramConversationState::query()
            ->where('telegram_user_id', $tgUser->id)
            ->delete();
    }
}
