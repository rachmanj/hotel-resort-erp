<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'telegram_user_id',
    'flow',
    'step',
    'payload',
    'expires_at',
])]
class TelegramConversationState extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
