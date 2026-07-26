<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'hotel_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function homeHotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class, 'hotel_id');
    }

    public function hotels(): BelongsToMany
    {
        return $this->belongsToMany(Hotel::class);
    }

    public function telegramUser(): HasOne
    {
        return $this->hasOne(TelegramUser::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hotel_id === null && $this->hasRole('admin');
    }

    public function accessibleHotels()
    {
        if ($this->hotel_id === null) {
            return Hotel::query()->where('is_active', true)->orderBy('name');
        }

        $grantedHotelIds = $this->hotels()->pluck('hotels.id');

        return Hotel::query()
            ->where('is_active', true)
            ->where(function ($query) use ($grantedHotelIds): void {
                $query->where('id', $this->hotel_id)
                    ->orWhereIn('id', $grantedHotelIds);
            })
            ->orderBy('name');
    }

    public function canAccessHotel(int $hotelId): bool
    {
        if ($this->hotel_id === null) {
            return Hotel::query()->whereKey($hotelId)->where('is_active', true)->exists();
        }

        if ($this->hotel_id === $hotelId) {
            return true;
        }

        return $this->hotels()->where('hotels.id', $hotelId)->exists();
    }
}
