<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHotel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'user_id',
    'hotel_id',
    'chat_id',
    'telegram_username',
    'link_code',
    'link_code_expires_at',
    'linked_at',
    'is_active',
])]
class TelegramUser extends Model
{
    use BelongsToHotel, Notifiable;

    public function routeNotificationForTelegram(): ?int
    {
        return $this->chat_id !== null ? (int) $this->chat_id : null;
    }

    protected static function nullableHotelId(): bool
    {
        return true;
    }

    protected function casts(): array
    {
        return [
            'link_code_expires_at' => 'datetime',
            'linked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function conversationStates(): HasMany
    {
        return $this->hasMany(TelegramConversationState::class);
    }

    public function isLinked(): bool
    {
        return $this->user_id !== null && $this->linked_at !== null;
    }
}
