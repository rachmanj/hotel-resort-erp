<?php

namespace App\Actions\Groups;

use App\Actions\Reservations\CheckOutGuestAction;
use App\Enums\GroupStatus;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\GroupBookingService;
use InvalidArgumentException;

class GroupCheckOutAction
{
    public function __construct(
        private CheckOutGuestAction $checkOutGuest,
        private GroupBookingService $groupBookingService,
        private DeductGroupDepositAction $deductGroupDeposit,
    ) {}

    /**
     * @return array{
     *     succeeded: list<array{reservation_id: int, reservation_code: string, room_number: string|null}>,
     *     failed: list<array{reservation_id: int, reservation_code: string, room_number: string|null, reason: string}>,
     * }
     */
    public function __invoke(ReservationGroup $group, ?User $performedBy = null, ?array $reservationRoomIds = null): array
    {
        $results = [
            'succeeded' => [],
            'failed' => [],
        ];

        $checkedInRooms = $this->groupBookingService->getCheckedInRooms($group)
            ->when($reservationRoomIds !== null, fn ($c) => $c->whereIn('id', $reservationRoomIds));

        if ($checkedInRooms->isEmpty()) {
            throw new InvalidArgumentException('No checked-in rooms available for check-out.');
        }

        foreach ($checkedInRooms as $reservationRoom) {
            $reservation = $reservationRoom->reservation;

            try {
                ($this->checkOutGuest)($reservationRoom, $performedBy);

                $results['succeeded'][] = [
                    'reservation_id' => $reservation?->id ?? 0,
                    'reservation_code' => $reservation?->reservation_code ?? 'N/A',
                    'room_number' => $reservationRoom->room?->number,
                ];
            } catch (InvalidArgumentException $e) {
                $results['failed'][] = [
                    'reservation_id' => $reservation?->id ?? 0,
                    'reservation_code' => $reservation?->reservation_code ?? 'N/A',
                    'room_number' => $reservationRoom->room?->number,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        $this->groupBookingService->refreshGroupStatus($group->fresh());

        $group = $group->fresh();
        if ($group->status === GroupStatus::CheckedOut) {
            ($this->deductGroupDeposit)($group, $performedBy);
        }

        if ($performedBy !== null) {
            $successCount = count($results['succeeded']);
            $failCount = count($results['failed']);
            ActivityLogObserver::logCustom(
                $group,
                'group_checkout',
                "Group {$group->group_code} check-out: {$successCount} succeeded, {$failCount} failed by {$performedBy->name}",
                $performedBy->id,
            );
        }

        return $results;
    }
}
