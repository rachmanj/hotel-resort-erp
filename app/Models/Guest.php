<?php

namespace App\Models;

use App\Enums\GuestIdType;
use App\Enums\VipTier;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'full_name',
    'id_number',
    'id_type',
    'phone',
    'email',
    'address',
    'nationality',
    'id_document_path',
    'vip_tier',
    'is_blacklisted',
    'blacklist_reason',
])]
class Guest extends Model
{
    use LogsActivity, Notifiable;

    protected function casts(): array
    {
        return [
            'id_type' => GuestIdType::class,
            'vip_tier' => VipTier::class,
            'is_blacklisted' => 'boolean',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Nomor WhatsApp untuk notifikasi (format internasional 62...).
     */
    public function routeNotificationForWhatsApp(): ?string
    {
        if (blank($this->phone)) {
            return null;
        }

        return \App\WhatsApp\WhatsAppResponder::normalizePhone($this->phone);
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(GuestPreference::class);
    }

    public function stays(): HasMany
    {
        return $this->hasMany(GuestStay::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(GuestIncident::class);
    }

    public function folios(): HasMany
    {
        return $this->hasMany(Folio::class);
    }
}
