<?php

namespace App\Actions\Reservations;

use App\Enums\CreatedVia;
use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateReservationAction
{
    public function __construct(
        private AvailabilityService $availabilityService,
    ) {}

    /**
     * @param  array{
     *     guest_id?: int|null,
     *     guest?: array<string, mixed>|null,
     *     hotel_id: int,
     *     arrival_date: string,
     *     departure_date: string,
     *     room_type_id: int,
     *     room_id?: int|null,
     *     rate_plan_id?: int|null,
     *     adults?: int,
     *     children?: int,
     *     special_requests?: string|null,
     *     source?: string,
     *     created_by?: int|null,
     *     created_via?: string,
     * }  $data
     */
    public function __invoke(array $data, ?User $performedBy = null): Reservation
    {
        return DB::transaction(function () use ($data, $performedBy): Reservation {
            $checkin = Carbon::parse($data['arrival_date'])->startOfDay();
            $checkout = Carbon::parse($data['departure_date'])->startOfDay();

            if ($checkout->lte($checkin)) {
                throw new \InvalidArgumentException('Departure date must be after arrival date.');
            }

            $guest = $this->resolveGuest($data);
            $roomId = $data['room_id'] ?? null;
            $ratePlanId = $data['rate_plan_id'] ?? null;
            $nightlyRate = $this->resolveNightlyRate($ratePlanId, $data['room_type_id']);

            $this->availabilityService->lockOverlappingForHotel($data['hotel_id'], $checkin, $checkout);

            if ($roomId !== null) {
                $room = Room::query()->findOrFail($roomId);
                $this->availabilityService->assertRoomAvailable($room, $checkin, $checkout);
            } else {
                $available = $this->availabilityService->getAvailableRooms(
                    $data['room_type_id'],
                    $checkin,
                    $checkout,
                    $data['hotel_id'],
                );

                if ($available->isEmpty()) {
                    throw new RoomNotAvailableException('No rooms available for the selected room type and dates.');
                }

                $roomId = $available->first()->id;
            }

            $reservation = Reservation::query()->create([
                'hotel_id' => $data['hotel_id'],
                'reservation_code' => $this->generateReservationCode(),
                'guest_id' => $guest->id,
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

            ReservationRoom::query()->create([
                'reservation_id' => $reservation->id,
                'room_id' => $roomId,
                'room_type_id' => $data['room_type_id'],
                'rate_plan_id' => $ratePlanId,
                'nightly_rate' => $nightlyRate,
                'status' => ReservationRoomStatus::Booked->value,
            ]);

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
    }

    /**
     * @param  array{
     *     guest_id?: int|null,
     *     guest?: array<string, mixed>|null,
     *     hotel_id: int,
     *     arrival_date: string,
     *     departure_date: string,
     *     room_type_id: int,
     *     room_id?: int|null,
     *     rate_plan_id?: int|null,
     *     adults?: int,
     *     children?: int,
     *     special_requests?: string|null,
     *     source?: string,
     *     created_by?: int|null,
     *     created_via?: string,
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

    private function resolveNightlyRate(?int $ratePlanId, int $roomTypeId): string
    {
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
