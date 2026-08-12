<?php

namespace App\Actions\Groups;

use App\Actions\Reservations\CreateReservationAction;
use App\Enums\GroupInvoiceMode;
use App\Enums\GroupStatus;
use App\Enums\GroupType;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\GroupBookingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateGroupAction
{
    public function __construct(
        private CreateReservationAction $createReservation,
        private GroupBookingService $groupBookingService,
    ) {}

    /**
     * @param  array{
     *     hotel_id: int,
     *     name: string,
     *     group_type: string,
     *     pic_guest_id?: int|null,
     *     company_id?: int|null,
     *     invoice_mode?: string,
     *     deposit_amount?: float,
     *     special_requests?: string|null,
     *     arrival_date?: string|null,
     *     departure_date?: string|null,
     *     room_selections?: list<array{room_type_id: int, room_id?: int|null, rate_plan_id?: int|null}>,
     *     reservation_data?: array<string, mixed>|null,
     * }  $data
     */
    public function __invoke(array $data, ?User $performedBy = null): ReservationGroup
    {
        return DB::transaction(function () use ($data, $performedBy): ReservationGroup {
            $groupType = GroupType::from($data['group_type']);

            $group = ReservationGroup::query()->create([
                'hotel_id' => $data['hotel_id'],
                'group_code' => ReservationGroup::generateGroupCode(),
                'group_type' => $groupType->value,
                'name' => $data['name'],
                'pic_guest_id' => $data['pic_guest_id'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'invoice_mode' => $data['invoice_mode'] ?? GroupInvoiceMode::PerRoom->value,
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'status' => GroupStatus::Draft->value,
                'special_requests' => $data['special_requests'] ?? null,
                'created_by' => $performedBy?->id,
            ]);

            if ($groupType === GroupType::SingleMultiRoom) {
                $this->createTypeAReservation($group, $data, $performedBy);
            }

            $this->groupBookingService->syncGroupDates($group);
            $this->groupBookingService->refreshGroupStatus($group->fresh());

            $group = $group->fresh(['picGuest', 'company', 'reservations.reservationRooms.room']);

            if ($performedBy !== null) {
                ActivityLogObserver::logCustom(
                    $group,
                    'created',
                    "Group {$group->group_code} ({$group->name}) created by {$performedBy->name}",
                    $performedBy->id,
                );
            }

            return $group;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createTypeAReservation(ReservationGroup $group, array $data, ?User $performedBy): void
    {
        $roomSelections = $data['room_selections'] ?? [];

        if ($roomSelections === []) {
            throw new InvalidArgumentException('Type A group requires at least one room selection.');
        }

        $reservationData = $data['reservation_data'] ?? [];

        if (! isset($reservationData['guest_id']) && ! isset($reservationData['guest'])) {
            if ($group->pic_guest_id === null) {
                throw new InvalidArgumentException('A guest or PIC is required for Type A group booking.');
            }

            $reservationData['guest_id'] = $group->pic_guest_id;
        }

        $reservationData['hotel_id'] = $group->hotel_id;
        $reservationData['arrival_date'] = $data['arrival_date'] ?? $reservationData['arrival_date'] ?? null;
        $reservationData['departure_date'] = $data['departure_date'] ?? $reservationData['departure_date'] ?? null;
        $reservationData['room_selections'] = $roomSelections;
        $reservationData['reservation_group_id'] = $group->id;
        $reservationData['created_by'] = $performedBy?->id;

        if ($reservationData['arrival_date'] === null || $reservationData['departure_date'] === null) {
            throw new InvalidArgumentException('Arrival and departure dates are required for Type A group booking.');
        }

        ($this->createReservation)($reservationData, $performedBy);

        $group->update(['status' => GroupStatus::Confirmed->value]);
    }
}
