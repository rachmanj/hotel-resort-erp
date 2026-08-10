<?php

namespace App\Http\Controllers;

use App\Actions\Reservations\CancelReservationAction;
use App\Actions\Reservations\CreateReservationAction;
use App\Enums\FolioType;
use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Http\Requests\CancelReservationRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function index(Request $request): Response
    {
        $reservations = Reservation::query()
            ->with(['guest:id,full_name,phone', 'reservationRooms.room:id,number', 'reservationRooms.roomType:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('arrival_date', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('departure_date', '<=', $request->string('date_to')))
            ->when($request->string('guest_search')->isNotEmpty(), function ($q) use ($request): void {
                $search = $request->string('guest_search')->toString();
                $q->whereHas('guest', function ($guestQuery) use ($search): void {
                    $guestQuery->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('id_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('arrival_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'reservation_code' => $reservation->reservation_code,
                'status' => $reservation->status->value,
                'status_label' => $reservation->status->label(),
                'status_color' => $reservation->status->color(),
                'source' => $reservation->source->value,
                'source_label' => $reservation->source->label(),
                'arrival_date' => $reservation->arrival_date->toDateString(),
                'departure_date' => $reservation->departure_date->toDateString(),
                'adults' => $reservation->adults,
                'children' => $reservation->children,
                'guest' => $reservation->guest?->only(['id', 'full_name', 'phone']),
                'rooms' => $reservation->reservationRooms->map(fn ($rr) => [
                    'room_number' => $rr->room?->number,
                    'room_type' => $rr->roomType?->name,
                    'nightly_rate' => $rr->nightly_rate,
                ]),
            ]);

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'statuses' => collect(ReservationStatus::cases())->map(fn (ReservationStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
            'sources' => collect(ReservationSource::cases())->map(fn (ReservationSource $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
            'filters' => $request->only(['status', 'source', 'date_from', 'date_to', 'guest_search']),
        ]);
    }

    public function create(AvailabilityService $availabilityService, Request $request): Response
    {
        $arrival = $request->string('arrival_date')->toString() ?: now()->toDateString();
        $departure = $request->string('departure_date')->toString() ?: now()->addDay()->toDateString();

        $checkin = Carbon::parse($arrival)->startOfDay();
        $checkout = Carbon::parse($departure)->startOfDay();

        $roomTypes = RoomType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'base_rate', 'max_occupancy']);

        $ratePlans = RatePlan::query()
            ->where('is_active', true)
            ->with(['roomType:id,name', 'season:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (RatePlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'room_type_id' => $plan->room_type_id,
                'nightly_rate' => $plan->nightly_rate,
                'rate_type' => $plan->rate_type->value,
                'season' => $plan->season?->only(['id', 'name']),
            ]);

        return Inertia::render('Reservations/Create', [
            'roomTypes' => $roomTypes,
            'ratePlans' => $ratePlans,
            'availability' => $availabilityService->getAvailability($checkin, $checkout),
            'defaults' => [
                'arrival_date' => $arrival,
                'departure_date' => $departure,
            ],
            'sources' => collect(ReservationSource::cases())->map(fn (ReservationSource $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function store(
        StoreReservationRequest $request,
        CreateReservationAction $createReservation,
    ): RedirectResponse {
        $hotelId = session('current_hotel_id');

        if ($hotelId === null) {
            return back()->with('error', 'No hotel context selected.');
        }

        try {
            $reservation = $createReservation([
                ...$request->validated(),
                'hotel_id' => $hotelId,
                'created_by' => $request->user()?->id,
                'created_via' => 'web',
            ]);
        } catch (RoomNotAvailableException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('success', 'Reservation created successfully.');
    }

    public function edit(AvailabilityService $availabilityService, Request $request, Reservation $reservation): Response
    {
        $reservation->load(['guest', 'reservationRooms.room', 'reservationRooms.roomType', 'reservationRooms.ratePlan']);

        $arrival = $request->string('arrival_date')->toString() ?: $reservation->arrival_date->toDateString();
        $departure = $request->string('departure_date')->toString() ?: $reservation->departure_date->toDateString();

        $checkin = Carbon::parse($arrival)->startOfDay();
        $checkout = Carbon::parse($departure)->startOfDay();

        $reservationRoom = $reservation->reservationRooms->first();

        return Inertia::render('Reservations/Edit', [
            'reservation' => [
                'id' => $reservation->id,
                'reservation_code' => $reservation->reservation_code,
                'arrival_date' => $reservation->arrival_date->toDateString(),
                'departure_date' => $reservation->departure_date->toDateString(),
                'adults' => $reservation->adults,
                'children' => $reservation->children,
                'special_requests' => $reservation->special_requests,
                'source' => $reservation->source->value,
                'guest_id' => $reservation->guest_id,
                'guest' => $reservation->guest ? [
                    'full_name' => $reservation->guest->full_name,
                    'phone' => $reservation->guest->phone ?? '',
                    'email' => $reservation->guest->email ?? '',
                    'id_number' => $reservation->guest->id_number ?? '',
                    'nationality' => $reservation->guest->nationality ?? '',
                ] : null,
                'room_type_id' => $reservationRoom?->room_type_id,
                'room_id' => $reservationRoom?->room_id,
                'rate_plan_id' => $reservationRoom?->rate_plan_id,
            ],
            'roomTypes' => RoomType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'base_rate', 'max_occupancy']),
            'ratePlans' => RatePlan::query()
                ->where('is_active', true)
                ->with(['roomType:id,name', 'season:id,name'])
                ->orderBy('name')
                ->get()
                ->map(fn (RatePlan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'room_type_id' => $plan->room_type_id,
                    'nightly_rate' => $plan->nightly_rate,
                    'rate_type' => $plan->rate_type->value,
                    'season' => $plan->season?->only(['id', 'name']),
                ]),
            'availability' => $availabilityService->getAvailability($checkin, $checkout, $reservation->hotel_id, $reservation->id),
            'sources' => collect(ReservationSource::cases())->map(fn (ReservationSource $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function update(
        UpdateReservationRequest $request,
        Reservation $reservation,
        AvailabilityService $availabilityService,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $reservation, $availabilityService): void {
                $checkin = Carbon::parse($validated['arrival_date'])->startOfDay();
                $checkout = Carbon::parse($validated['departure_date'])->startOfDay();

                $availabilityService->lockOverlappingForHotel($reservation->hotel_id, $checkin, $checkout);

                $roomId = $validated['room_id'] ?? null;

                if ($roomId !== null) {
                    $room = Room::query()->findOrFail($roomId);
                    $availabilityService->assertRoomAvailable($room, $checkin, $checkout, $reservation->id);
                }

                if (isset($validated['guest_id'])) {
                    $reservation->update(['guest_id' => $validated['guest_id']]);
                } elseif (isset($validated['guest']) && $reservation->guest !== null) {
                    $reservation->guest->update(array_filter(
                        $validated['guest'],
                        fn ($value) => $value !== null && $value !== '',
                    ));
                }

                $reservation->update([
                    'arrival_date' => $validated['arrival_date'],
                    'departure_date' => $validated['departure_date'],
                    'adults' => $validated['adults'] ?? $reservation->adults,
                    'children' => $validated['children'] ?? $reservation->children,
                    'special_requests' => $validated['special_requests'] ?? null,
                    'source' => $validated['source'] ?? $reservation->source->value,
                ]);

                $reservationRoom = $reservation->reservationRooms()->first();

                if ($reservationRoom !== null) {
                    $nightlyRate = $this->resolveNightlyRate(
                        $validated['rate_plan_id'] ?? null,
                        $validated['room_type_id'],
                    );

                    $reservationRoom->update([
                        'room_type_id' => $validated['room_type_id'],
                        'room_id' => $roomId,
                        'rate_plan_id' => $validated['rate_plan_id'] ?? null,
                        'nightly_rate' => $nightlyRate,
                    ]);
                }
            });
        } catch (RoomNotAvailableException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('reservations.show', $reservation)
            ->with('success', 'Reservation updated successfully.');
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

    public function show(Reservation $reservation): Response
    {
        $reservation->load([
            'guest',
            'createdBy:id,name',
            'reservationRooms.room',
            'reservationRooms.roomType',
            'reservationRooms.ratePlan',
            'folios',
        ]);

        $masterFolio = $reservation->folios->first(fn ($f) => $f->type === FolioType::Master);

        return Inertia::render('Reservations/Show', [
            'reservation' => [
                'id' => $reservation->id,
                'reservation_code' => $reservation->reservation_code,
                'status' => $reservation->status->value,
                'status_label' => $reservation->status->label(),
                'status_color' => $reservation->status->color(),
                'source' => $reservation->source->value,
                'source_label' => $reservation->source->label(),
                'arrival_date' => $reservation->arrival_date->toDateString(),
                'departure_date' => $reservation->departure_date->toDateString(),
                'adults' => $reservation->adults,
                'children' => $reservation->children,
                'special_requests' => $reservation->special_requests,
                'cancelled_reason' => $reservation->cancelled_reason,
                'created_by' => $reservation->createdBy?->only(['id', 'name']),
                'guest' => $reservation->guest?->only([
                    'id', 'full_name', 'phone', 'email', 'id_number', 'id_type', 'nationality', 'address',
                    'vip_tier', 'is_blacklisted',
                ]),
                'reservation_rooms' => $reservation->reservationRooms->map(fn ($rr) => [
                    'id' => $rr->id,
                    'status' => $rr->status->value,
                    'status_label' => $rr->status->label(),
                    'nightly_rate' => $rr->nightly_rate,
                    'room' => $rr->room?->only(['id', 'number']),
                    'room_type' => $rr->roomType?->only(['id', 'name', 'code']),
                    'rate_plan' => $rr->ratePlan?->only(['id', 'name']),
                ]),
            ],
            'folio' => $masterFolio ? [
                'id' => $masterFolio->id,
                'folio_no' => $masterFolio->folio_no,
                'status' => $masterFolio->status->value,
            ] : null,
            'canCancel' => request()->user()?->can('reservations.cancel') ?? false,
            'canCheckIn' => request()->user()?->can('reservations.checkin') ?? false,
            'canCheckOut' => request()->user()?->can('reservations.checkout') ?? false,
            'canViewFolio' => request()->user()?->can('folios.view') ?? false,
        ]);
    }

    public function cancel(
        CancelReservationRequest $request,
        Reservation $reservation,
        CancelReservationAction $cancelReservation,
    ): RedirectResponse {
        if ($reservation->status === ReservationStatus::Cancelled) {
            return back()->with('error', 'Reservation is already cancelled.');
        }

        $cancelReservation($reservation, $request->validated());

        return back()->with('success', 'Reservation cancelled successfully.');
    }
}
