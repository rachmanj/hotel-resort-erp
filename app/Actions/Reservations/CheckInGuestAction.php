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
use App\Observers\ActivityLogObserver;
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

    /**
     * @param  list<int>|null  $reservationRoomIds  Check in specific rooms only (multi-room partial check-in)
     */
    public function __invoke(Reservation $reservation, ?User $performedBy = null, ?array $reservationRoomIds = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $performedBy, $reservationRoomIds): Reservation {
            $reservation = Reservation::query()->lockForUpdate()->findOrFail($reservation->id);
            $reservation->load(['guest', 'reservationRooms.room.roomType', 'reservationGroup']);

            if (! in_array($reservation->status, [ReservationStatus::Confirmed, ReservationStatus::CheckedIn], true)) {
                throw new InvalidArgumentException('Reservation must be in confirmed or partially checked-in status to check in.');
            }

            if ($reservation->guest?->is_blacklisted) {
                throw new InvalidArgumentException('Guest is blacklisted and cannot check in.');
            }

            $roomsToCheckIn = $reservation->reservationRooms
                ->filter(fn (ReservationRoom $rr) => $rr->status === ReservationRoomStatus::Booked)
                ->when($reservationRoomIds !== null, fn ($c) => $c->whereIn('id', $reservationRoomIds));

            if ($roomsToCheckIn->isEmpty()) {
                throw new InvalidArgumentException('No booked rooms available for check-in.');
            }

            $nights = max(1, Carbon::parse($reservation->arrival_date)->diffInDays($reservation->departure_date));

            $companyId = $reservation->reservationGroup?->company_id;

            $folio = $this->folioPostingService->findOrCreateMasterFolio(
                $reservation->hotel_id,
                $reservation->id,
                $reservation->guest_id,
                $companyId,
            );

            foreach ($roomsToCheckIn as $reservationRoom) {
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
                    "Room {$reservationRoom->room?->number} · {$nights} night(s)",
                    (float) $reservationRoom->nightly_rate,
                    $nights,
                    ReservationRoom::class,
                    $reservationRoom->id,
                    $performedBy,
                    revenueCategoryId: $reservationRoom->room?->roomType?->revenue_category_id,
                );
            }

            $allCheckedIn = $reservation->reservationRooms()
                ->where('status', '!=', ReservationRoomStatus::CheckedIn->value)
                ->where('status', '!=', ReservationRoomStatus::CheckedOut->value)
                ->doesntExist();

            $reservation->update([
                'status' => $allCheckedIn || $reservation->status === ReservationStatus::CheckedIn
                    ? ReservationStatus::CheckedIn->value
                    : ReservationStatus::CheckedIn->value,
            ]);

            $reservation = $reservation->fresh(['guest', 'reservationRooms.room', 'reservationGroup']);

            if ($performedBy !== null) {
                $roomCount = $roomsToCheckIn->count();
                ActivityLogObserver::logCustom(
                    $reservation,
                    'checked_in',
                    "Reservation {$reservation->reservation_code} · {$roomCount} room(s) checked in by {$performedBy->name}",
                    $performedBy->id,
                );
            }

            $this->dispatchVipAlert($reservation);

            return $reservation;
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
