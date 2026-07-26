<?php

namespace App\Actions\Reservations;

use App\Enums\FolioItemType;
use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationStatus;
use App\Enums\RoomStatus;
use App\Enums\VipTier;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\TelegramUser;
use App\Models\User;
use App\Notifications\VipGuestAlertNotification;
use App\Services\FolioPostingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class CheckInGuestAction
{
    public function __construct(
        private FolioPostingService $folioPostingService,
    ) {}

    public function __invoke(Reservation $reservation, ?User $performedBy = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $performedBy): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $reservation->load(['guest', 'reservationRooms.room']);

            if ($reservation->status !== ReservationStatus::Confirmed) {
                throw new InvalidArgumentException('Reservation must be in confirmed status to check in.');
            }

            if ($reservation->guest?->is_blacklisted) {
                throw new InvalidArgumentException('Guest is blacklisted and cannot check in.');
            }

            $nights = max(1, Carbon::parse($reservation->arrival_date)->diffInDays($reservation->departure_date));

            $folio = $this->folioPostingService->findOrCreateMasterFolio(
                $reservation->hotel_id,
                $reservation->id,
                $reservation->guest_id,
            );

            foreach ($reservation->reservationRooms as $reservationRoom) {
                $reservationRoom->update([
                    'status' => ReservationRoomStatus::CheckedIn->value,
                    'check_in_at' => now(),
                ]);

                if ($reservationRoom->room !== null) {
                    $reservationRoom->room->update([
                        'status' => RoomStatus::OccupiedClean->value,
                    ]);
                }

                $this->folioPostingService->postCharge(
                    $folio,
                    FolioItemType::Room->value,
                    "Room {$reservationRoom->room?->number} — {$nights} night(s)",
                    (float) $reservationRoom->nightly_rate,
                    $nights,
                    ReservationRoom::class,
                    $reservationRoom->id,
                    $performedBy,
                );
            }

            $reservation->update([
                'status' => ReservationStatus::CheckedIn->value,
            ]);

            $this->dispatchVipAlert($reservation);

            return $reservation->fresh(['guest', 'reservationRooms.room']);
        });
    }

    private function dispatchVipAlert(Reservation $reservation): void
    {
        $guest = $reservation->guest;

        if ($guest === null || $guest->vip_tier === VipTier::None) {
            return;
        }

        $telegramUsers = TelegramUser::query()
            ->where('is_active', true)
            ->whereNotNull('chat_id')
            ->whereHas('user', function ($query): void {
                $query->role(['front_office', 'manager', 'admin']);
            })
            ->when($reservation->hotel_id, fn ($q) => $q->where('hotel_id', $reservation->hotel_id))
            ->get();

        Notification::send($telegramUsers, new VipGuestAlertNotification($reservation));
    }
}
