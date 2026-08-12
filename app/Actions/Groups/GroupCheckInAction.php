<?php

namespace App\Actions\Groups;

use App\Actions\Reservations\CheckInGuestAction;
use App\Enums\ReservationStatus;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\GroupBookingService;
use InvalidArgumentException;

class GroupCheckInAction
{
    public function __construct(
        private CheckInGuestAction $checkInGuest,
        private GroupBookingService $groupBookingService,
    ) {}

    /**
     * @return array{
     *     succeeded: list<array{reservation_id: int, reservation_code: string}>,
     *     failed: list<array{reservation_id: int, reservation_code: string, reason: string}>,
     * }
     */
    public function __invoke(ReservationGroup $group, ?User $performedBy = null, ?array $reservationIds = null): array
    {
        $results = [
            'succeeded' => [],
            'failed' => [],
        ];

        $reservations = $this->groupBookingService->getMemberReservations($group)
            ->filter(fn ($r) => $r->status === ReservationStatus::Confirmed)
            ->when($reservationIds !== null, fn ($c) => $c->whereIn('id', $reservationIds));

        if ($reservations->isEmpty()) {
            throw new InvalidArgumentException('No confirmed reservations available for check-in.');
        }

        foreach ($reservations as $reservation) {
            try {
                ($this->checkInGuest)($reservation, $performedBy);

                $results['succeeded'][] = [
                    'reservation_id' => $reservation->id,
                    'reservation_code' => $reservation->reservation_code,
                ];
            } catch (InvalidArgumentException $e) {
                $results['failed'][] = [
                    'reservation_id' => $reservation->id,
                    'reservation_code' => $reservation->reservation_code,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        $this->groupBookingService->refreshGroupStatus($group->fresh());

        if ($performedBy !== null) {
            $successCount = count($results['succeeded']);
            $failCount = count($results['failed']);
            ActivityLogObserver::logCustom(
                $group,
                'group_checkin',
                "Group {$group->group_code} check-in: {$successCount} succeeded, {$failCount} failed by {$performedBy->name}",
                $performedBy->id,
            );
        }

        return $results;
    }
}
