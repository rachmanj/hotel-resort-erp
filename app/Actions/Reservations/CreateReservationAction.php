<?php

namespace App\Actions\Reservations;

use App\Enums\CreatedVia;
use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Agent;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\AgentRateService;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateReservationAction
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private AgentRateService $agentRateService,
    ) {}

    /**
     * @param  array{
     *     guest_id?: int|null,
     *     guest?: array<string, mixed>|null,
     *     hotel_id: int,
     *     arrival_date: string,
     *     departure_date: string,
     *     room_type_id?: int,
     *     room_id?: int|null,
     *     rate_plan_id?: int|null,
     *     room_selections?: list<array{room_type_id: int, room_id?: int|null, rate_plan_id?: int|null}>,
     *     reservation_group_id?: int|null,
     *     adults?: int,
     *     children?: int,
     *     special_requests?: string|null,
     *     source?: string,
     *     agent_id?: int|null,
     *     ota_fee_id?: int|null,
     *     external_booking_id?: string|null,
     *     created_by?: int|null,
     *     created_via?: string,
     * }  $data
     */
    public function __invoke(array $data, ?User $performedBy = null): Reservation
    {
        $reservation = DB::transaction(function () use ($data, $performedBy): Reservation {
            $checkin = Carbon::parse($data['arrival_date'])->startOfDay();
            $checkout = Carbon::parse($data['departure_date'])->startOfDay();

            if ($checkout->lte($checkin)) {
                throw new InvalidArgumentException('Departure date must be after arrival date.');
            }

            $guest = $this->resolveGuest($data);
            $roomSelections = $data['room_selections'] ?? null;

            if ($roomSelections === null) {
                if (! isset($data['room_type_id'])) {
                    throw new InvalidArgumentException('room_type_id is required when room_selections is not provided.');
                }

                $roomSelections = [[
                    'room_type_id' => $data['room_type_id'],
                    'room_id' => $data['room_id'] ?? null,
                    'rate_plan_id' => $data['rate_plan_id'] ?? null,
                ]];
            }

            $this->availabilityService->lockOverlappingForHotel($data['hotel_id'], $checkin, $checkout);

            $resolvedRooms = $this->resolveRoomSelections($roomSelections, $checkin, $checkout, $data['hotel_id'], $data);

            $reservation = Reservation::query()->create([
                'hotel_id' => $data['hotel_id'],
                'reservation_code' => $this->generateReservationCode(),
                'external_booking_id' => $data['external_booking_id'] ?? null,
                'guest_id' => $guest->id,
                'agent_id' => $data['agent_id'] ?? null,
                'ota_fee_id' => $data['ota_fee_id'] ?? null,
                'reservation_group_id' => $data['reservation_group_id'] ?? null,
                'source' => $data['source'] ?? ReservationSource::Walkin->value,
                'status' => ReservationStatus::Confirmed->value,
                'arrival_date' => $checkin->toDateString(),
                'departure_date' => $checkout->toDateString(),
                'adults' => $data['adults'] ?? 1,
                'children' => $data['children'] ?? 0,
                'special_requests' => $data['special_requests'] ?? null,
                'created_by' => $data['created_by'] ?? null,
                'created_via' => $data['created_via'] ?? CreatedVia::Web->value,
            ]);

            foreach ($resolvedRooms as $roomData) {
                ReservationRoom::query()->create([
                    'reservation_id' => $reservation->id,
                    'room_id' => $roomData['room_id'],
                    'room_type_id' => $roomData['room_type_id'],
                    'rate_plan_id' => $roomData['rate_plan_id'],
                    'nightly_rate' => $roomData['nightly_rate'],
                    'status' => ReservationRoomStatus::Booked->value,
                ]);
            }

            $reservation = $reservation->load(['guest', 'reservationRooms.room', 'reservationRooms.roomType']);

            $actor = $performedBy ?? (($data['created_by'] ?? null) !== null ? User::query()->find($data['created_by']) : null);

            if ($actor !== null) {
                ActivityLogObserver::logCustom(
                    $reservation,
                    'created',
                    "Reservation {$reservation->reservation_code} created for {$reservation->guest?->full_name} by {$actor->name}",
                    $actor->id,
                );
            }

            return $reservation;
        });

        return $reservation;
    }

    /**
     * @param  list<array{room_type_id: int, room_id?: int|null, rate_plan_id?: int|null}>  $roomSelections
     * @return list<array{room_id: int, room_type_id: int, rate_plan_id: int|null, nightly_rate: string}>
     */
    private function resolveRoomSelections(array $roomSelections, Carbon $checkin, Carbon $checkout, int $hotelId, array $data = []): array
    {
        $resolved = [];
        $usedRoomIds = [];

        foreach ($roomSelections as $selection) {
            $roomTypeId = $selection['room_type_id'];
            $roomId = $selection['room_id'] ?? null;
            $ratePlanId = $selection['rate_plan_id'] ?? null;
            $nightlyRate = $this->resolveNightlyRate($ratePlanId, $roomTypeId, $checkin, $checkout, $data);

            if ($roomId !== null) {
                $room = Room::query()->findOrFail($roomId);
                $this->availabilityService->assertRoomAvailable($room, $checkin, $checkout);
            } else {
                $available = $this->availabilityService->getAvailableRooms(
                    $roomTypeId,
                    $checkin,
                    $checkout,
                    $hotelId,
                )->reject(fn (Room $room) => in_array($room->id, $usedRoomIds, true));

                if ($available->isEmpty()) {
                    throw new RoomNotAvailableException('No rooms available for the selected room type and dates.');
                }

                $roomId = $available->first()->id;
            }

            $usedRoomIds[] = $roomId;

            $resolved[] = [
                'room_id' => $roomId,
                'room_type_id' => $roomTypeId,
                'rate_plan_id' => $ratePlanId,
                'nightly_rate' => $nightlyRate,
            ];
        }

        return $resolved;
    }

    /**
     * @param  array{
     *     guest_id?: int|null,
     *     guest?: array<string, mixed>|null,
     * }  $data
     */
    private function resolveGuest(array $data): Guest
    {
        if (isset($data['guest_id']) && $data['guest_id'] !== null) {
            return Guest::query()->findOrFail($data['guest_id']);
        }

        $guestData = $data['guest'] ?? [];

        if (isset($guestData['phone']) && $guestData['phone'] !== '') {
            $existing = Guest::query()->where('phone', $guestData['phone'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        if (isset($guestData['id_number']) && $guestData['id_number'] !== '') {
            $existing = Guest::query()->where('id_number', $guestData['id_number'])->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        return Guest::query()->create([
            'full_name' => $guestData['full_name'] ?? 'Guest',
            'id_number' => $guestData['id_number'] ?? null,
            'id_type' => $guestData['id_type'] ?? null,
            'phone' => $guestData['phone'] ?? null,
            'email' => $guestData['email'] ?? null,
            'address' => $guestData['address'] ?? null,
            'nationality' => $guestData['nationality'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveNightlyRate(
        ?int $ratePlanId,
        int $roomTypeId,
        ?Carbon $checkin = null,
        ?Carbon $checkout = null,
        array $data = [],
    ): string {
        if (isset($data['agent_id']) && $data['agent_id'] !== null && $checkin !== null && $checkout !== null) {
            $agent = Agent::query()->find($data['agent_id']);

            if ($agent !== null) {
                $agentRate = $this->agentRateService->resolveNightlyRate(
                    $agent,
                    $roomTypeId,
                    $checkin,
                    $checkout,
                    $ratePlanId,
                );

                if ($agentRate !== null) {
                    return $agentRate;
                }
            }
        }

        if ($ratePlanId !== null) {
            $ratePlan = RatePlan::query()->findOrFail($ratePlanId);

            return (string) $ratePlan->nightly_rate;
        }

        $roomType = RoomType::query()->findOrFail($roomTypeId);

        return (string) $roomType->base_rate;
    }

    private function generateReservationCode(): string
    {
        $datePrefix = now()->format('Ymd');
        $prefix = "RES-{$datePrefix}-";

        $lastCode = Reservation::query()
            ->withoutGlobalScope('hotel')
            ->where('reservation_code', 'like', $prefix.'%')
            ->orderByDesc('reservation_code')
            ->value('reservation_code');

        $sequence = 1;
        if ($lastCode !== null) {
            $lastSequence = (int) substr($lastCode, -4);
            $sequence = $lastSequence + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
