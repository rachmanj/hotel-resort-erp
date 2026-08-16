<?php

namespace App\Telegram\Commands;

use App\Enums\HousekeepingAssignmentStatus;
use App\Enums\HousekeepingStatus;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\TelegramConversationState;
use App\Models\TelegramUser;
use App\Models\User;
use App\Services\HousekeepingService;
use App\Telegram\TelegramResponder;
use Carbon\Carbon;

class MyRoomsCommand extends BaseCommand
{
    public function __construct(
        TelegramResponder $responder,
        private HousekeepingService $housekeepingService,
    ) {
        parent::__construct($responder);
    }

    public function authorize(TelegramUser $tgUser): bool
    {
        return parent::authorize($tgUser) && ($tgUser->user?->can('rooms.view') ?? false);
    }

    public function handle(array $args, TelegramUser $tgUser, ?TelegramConversationState $state): void
    {
        if (! $this->requirePermission($tgUser, 'rooms.view')) {
            return;
        }

        $this->setHotelContext($tgUser);

        $user = $tgUser->user;

        if ($user !== null && $user->hasRole('housekeeping')) {
            $this->showHousekeepingAssignments($tgUser, $user);

            return;
        }

        $this->showFrontOfficeRooms($tgUser);
    }

    private function showHousekeepingAssignments(TelegramUser $tgUser, User $user): void
    {
        $assignments = $this->housekeepingService->getAssignmentsFor($user);

        if ($assignments->isEmpty()) {
            $this->reply($tgUser, '📋 No housekeeping assignments for today.');

            return;
        }

        $shift = $assignments->first()?->shift?->label() ?? 'Morning';

        $lines = $assignments->map(function ($assignment, int $index) {
            $room = $assignment->room;
            $hkStatus = $room !== null
                ? $this->housekeepingService->resolveHousekeepingStatus($room)
                : HousekeepingStatus::Dirty;

            $statusEmoji = match ($assignment->status) {
                HousekeepingAssignmentStatus::Done => '✅',
                HousekeepingAssignmentStatus::InProgress => '🟡',
                HousekeepingAssignmentStatus::Skipped => '⏭️',
                default => match ($hkStatus) {
                    HousekeepingStatus::Dirty => '🔴',
                    HousekeepingStatus::Cleaning => '🟡',
                    HousekeepingStatus::Clean, HousekeepingStatus::Ready => '✅',
                    default => '',
                },
            };

            $roomNumber = $room?->number ?? '?';
            $roomType = $room?->roomType?->name ?? '';
            $statusLabel = $assignment->status === HousekeepingAssignmentStatus::Done
                ? 'Done'
                : $hkStatus->label();

            return sprintf(
                '%d. %s Room %s (%s) · %s',
                $index + 1,
                $statusEmoji,
                $roomNumber,
                $roomType,
                $statusLabel,
            );
        })->implode("\n");

        $doneCount = $assignments->where('status', HousekeepingAssignmentStatus::Done)->count();
        $total = $assignments->count();

        $this->reply(
            $tgUser,
            "📋 Today's assignments ({$shift} shift):\n\n{$lines}\n\nProgress: {$doneCount}/{$total} done",
        );
    }

    private function showFrontOfficeRooms(TelegramUser $tgUser): void
    {
        $today = Carbon::today()->toDateString();

        $reservations = Reservation::query()
            ->with(['guest:id,full_name', 'reservationRooms.room:id,number', 'reservationRooms.roomType:id,name'])
            ->when($tgUser->hotel_id !== null, fn ($q) => $q->where('hotel_id', $tgUser->hotel_id))
            ->whereNotIn('status', [ReservationStatus::Cancelled->value, ReservationStatus::NoShow->value])
            ->where(function ($query) use ($today): void {
                $query->where('arrival_date', $today)
                    ->orWhere('departure_date', $today)
                    ->orWhere(function ($q) use ($today): void {
                        $q->where('arrival_date', '<=', $today)
                            ->where('departure_date', '>', $today);
                    });
            })
            ->orderBy('arrival_date')
            ->get();

        if ($reservations->isEmpty()) {
            $this->reply($tgUser, '📋 No active reservations for today.');

            return;
        }

        $lines = $reservations->map(function (Reservation $reservation) use ($today) {
            $room = $reservation->reservationRooms->first();
            $roomNumber = $room?->room?->number ?? 'TBA';
            $roomType = $room?->roomType?->name ?? 'Unknown';

            $tag = match (true) {
                $reservation->arrival_date->toDateString() === $today => '📥 Arrival',
                $reservation->departure_date->toDateString() === $today => '📤 Departure',
                default => '🏠 In-house',
            };

            return sprintf(
                "%s %s · Room %s (%s)\n   %s",
                $tag,
                $reservation->reservation_code,
                $roomNumber,
                $roomType,
                $reservation->guest?->full_name ?? 'Unknown',
            );
        })->implode("\n\n");

        $this->reply($tgUser, "📋 Today's Rooms\n\n{$lines}");
    }
}
