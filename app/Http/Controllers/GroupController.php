<?php

namespace App\Http\Controllers;

use App\Actions\Groups\AddReservationToGroupAction;
use App\Actions\Groups\CollectGroupDepositAction;
use App\Actions\Groups\CreateGroupAction;
use App\Actions\Groups\GenerateGroupInvoiceAction;
use App\Actions\Groups\GroupCheckInAction;
use App\Actions\Groups\GroupCheckOutAction;
use App\Actions\Groups\RemoveReservationFromGroupAction;
use App\Enums\GroupInvoiceMode;
use App\Enums\GroupStatus;
use App\Enums\GroupType;
use App\Enums\ReservationStatus;
use App\Exceptions\RoomNotAvailableException;
use App\Http\Requests\AddGroupReservationRequest;
use App\Http\Requests\GenerateGroupInvoiceRequest;
use App\Http\Requests\StoreGroupDepositRequest;
use App\Http\Requests\StoreGroupRequest;
use App\Models\Company;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\RoomType;
use App\Services\AvailabilityService;
use App\Services\GroupBookingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class GroupController extends Controller
{
    public function __construct(
        private GroupBookingService $groupBookingService,
        private AvailabilityService $availabilityService,
    ) {}

    public function index(Request $request): Response
    {
        $groups = ReservationGroup::query()
            ->with(['picGuest:id,full_name,phone', 'company:id,name'])
            ->withCount('reservations')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('group_type'), fn ($q) => $q->where('group_type', $request->string('group_type')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('arrival_date', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('departure_date', '<=', $request->string('date_to')))
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request): void {
                $search = $request->string('search')->toString();
                $q->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('group_code', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('arrival_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ReservationGroup $group) => [
                'id' => $group->id,
                'group_code' => $group->group_code,
                'name' => $group->name,
                'group_type' => $group->group_type->value,
                'group_type_label' => $group->group_type->label(),
                'status' => $group->status->value,
                'status_label' => $group->status->label(),
                'status_color' => $group->status->color(),
                'invoice_mode' => $group->invoice_mode->value,
                'invoice_mode_label' => $group->invoice_mode->label(),
                'arrival_date' => $group->arrival_date?->toDateString(),
                'departure_date' => $group->departure_date?->toDateString(),
                'deposit_amount' => (float) $group->deposit_amount,
                'deposit_paid_at' => $group->deposit_paid_at?->toDateTimeString(),
                'reservations_count' => $group->reservations_count,
                'room_count' => $this->groupBookingService->countRooms($group),
                'pic_guest' => $group->picGuest?->only(['id', 'full_name', 'phone']),
                'company' => $group->company?->only(['id', 'name']),
            ]);

        return Inertia::render('Groups/Index', [
            'groups' => $groups,
            'statuses' => collect(GroupStatus::cases())->map(fn (GroupStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
            'groupTypes' => collect(GroupType::cases())->map(fn (GroupType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'filters' => $request->only(['status', 'group_type', 'date_from', 'date_to', 'search']),
        ]);
    }

    public function create(Request $request): Response
    {
        $arrival = $request->string('arrival_date')->toString() ?: now()->toDateString();
        $departure = $request->string('departure_date')->toString() ?: now()->addDay()->toDateString();

        $checkin = Carbon::parse($arrival)->startOfDay();
        $checkout = Carbon::parse($departure)->startOfDay();

        return Inertia::render('Groups/Create', [
            'roomTypes' => RoomType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'base_rate', 'max_occupancy']),
            'ratePlans' => RatePlan::query()->with('season:id,name')->where('is_active', true)->orderBy('name')->get(),
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'availability' => $this->availabilityService->getAvailability($checkin, $checkout),
            'groupTypes' => collect(GroupType::cases())->map(fn (GroupType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'invoiceModes' => collect(GroupInvoiceMode::cases())->map(fn (GroupInvoiceMode $m) => [
                'value' => $m->value,
                'label' => $m->label(),
            ]),
            'defaults' => [
                'arrival_date' => $arrival,
                'departure_date' => $departure,
            ],
        ]);
    }

    public function store(StoreGroupRequest $request, CreateGroupAction $createGroup): RedirectResponse
    {
        $data = $request->validated();
        $data['hotel_id'] = (int) session('current_hotel_id');

        try {
            $group = $createGroup($data, $request->user());
        } catch (RoomNotAvailableException|InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('groups.show', $group)
            ->with('success', "Group {$group->group_code} created successfully.");
    }

    public function show(ReservationGroup $group): Response
    {
        $group->load(['picGuest', 'company', 'reservations.guest', 'reservations.reservationRooms.room', 'reservations.reservationRooms.roomType']);

        $reservations = $group->reservations->map(fn (Reservation $reservation) => [
            'id' => $reservation->id,
            'reservation_code' => $reservation->reservation_code,
            'status' => $reservation->status->value,
            'status_label' => $reservation->status->label(),
            'status_color' => $reservation->status->color(),
            'arrival_date' => $reservation->arrival_date->toDateString(),
            'departure_date' => $reservation->departure_date->toDateString(),
            'guest' => $reservation->guest?->only(['id', 'full_name', 'phone']),
            'reservation_rooms' => $reservation->reservationRooms->map(fn ($rr) => [
                'id' => $rr->id,
                'status' => $rr->status->value,
                'status_label' => $rr->status->label(),
                'room_number' => $rr->room?->number,
                'room_type' => $rr->roomType?->name,
                'can_check_out' => $rr->status->value === 'checked_in',
            ]),
            'can_check_in' => $reservation->status === ReservationStatus::Confirmed,
        ]);

        return Inertia::render('Groups/Show', [
            'group' => [
                'id' => $group->id,
                'group_code' => $group->group_code,
                'name' => $group->name,
                'group_type' => $group->group_type->value,
                'group_type_label' => $group->group_type->label(),
                'status' => $group->status->value,
                'status_label' => $group->status->label(),
                'status_color' => $group->status->color(),
                'invoice_mode' => $group->invoice_mode->value,
                'invoice_mode_label' => $group->invoice_mode->label(),
                'arrival_date' => $group->arrival_date?->toDateString(),
                'departure_date' => $group->departure_date?->toDateString(),
                'deposit_amount' => (float) $group->deposit_amount,
                'deposit_paid_at' => $group->deposit_paid_at?->toDateTimeString(),
                'deposit_balance' => $this->groupBookingService->getDepositBalance($group),
                'consolidated_balance' => $this->groupBookingService->getConsolidatedBalance($group),
                'special_requests' => $group->special_requests,
                'pic_guest' => $group->picGuest?->only(['id', 'full_name', 'phone', 'email']),
                'company' => $group->company?->only(['id', 'name']),
                'room_count' => $this->groupBookingService->countRooms($group),
            ],
            'reservations' => $reservations,
            'canManage' => request()->user()?->can('groups.manage') ?? false,
            'canCheckIn' => request()->user()?->can('groups.checkin') ?? false,
            'canCheckOut' => request()->user()?->can('groups.checkout') ?? false,
            'canInvoice' => request()->user()?->can('billing.invoice') ?? false,
        ]);
    }

    public function addReservation(
        AddGroupReservationRequest $request,
        ReservationGroup $group,
        AddReservationToGroupAction $addReservation,
    ): RedirectResponse {
        $data = $request->validated();

        try {
            if (isset($data['reservation_id'])) {
                $existing = Reservation::query()->findOrFail($data['reservation_id']);
                $addReservation($group, $existing, null, $request->user());
            } else {
                $reservationData = [
                    'arrival_date' => $data['arrival_date'],
                    'departure_date' => $data['departure_date'],
                    'room_type_id' => $data['room_type_id'],
                    'room_id' => $data['room_id'] ?? null,
                    'rate_plan_id' => $data['rate_plan_id'] ?? null,
                    'guest_id' => $data['guest_id'] ?? $group->pic_guest_id,
                    'adults' => $data['adults'] ?? 1,
                    'children' => $data['children'] ?? 0,
                    'special_requests' => $data['special_requests'] ?? null,
                    'source' => $data['source'] ?? 'phone',
                ];
                $addReservation($group, null, $reservationData, $request->user());
            }
        } catch (RoomNotAvailableException|InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Reservation added to group.');
    }

    public function removeReservation(
        ReservationGroup $group,
        Reservation $reservation,
        RemoveReservationFromGroupAction $removeReservation,
    ): RedirectResponse {
        try {
            $removeReservation($group, $reservation, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Reservation removed from group.');
    }

    public function checkIn(ReservationGroup $group, GroupCheckInAction $checkIn): RedirectResponse
    {
        try {
            $results = $checkIn($group, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $success = count($results['succeeded']);
        $failed = count($results['failed']);

        if ($failed > 0) {
            $reasons = collect($results['failed'])->map(fn ($f) => "{$f['reservation_code']}: {$f['reason']}")->implode('; ');

            return back()->with('error', "Check-in partial: {$success} succeeded, {$failed} failed. {$reasons}");
        }

        return back()->with('success', "All {$success} reservation(s) checked in successfully.");
    }

    public function checkOut(ReservationGroup $group, GroupCheckOutAction $checkOut): RedirectResponse
    {
        try {
            $results = $checkOut($group, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $success = count($results['succeeded']);
        $failed = count($results['failed']);

        if ($failed > 0) {
            $reasons = collect($results['failed'])->map(fn ($f) => "{$f['reservation_code']}: {$f['reason']}")->implode('; ');

            return back()->with('error', "Check-out partial: {$success} succeeded, {$failed} failed. {$reasons}");
        }

        return back()->with('success', "All {$success} room(s) checked out successfully.");
    }

    public function storeDeposit(
        StoreGroupDepositRequest $request,
        ReservationGroup $group,
        CollectGroupDepositAction $collectDeposit,
    ): RedirectResponse {
        $data = $request->validated();

        try {
            $collectDeposit(
                $group,
                (float) $data['amount'],
                $data['method'],
                $data['reference_no'] ?? null,
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Deposit collected successfully.');
    }

    public function generateInvoice(
        GenerateGroupInvoiceRequest $request,
        ReservationGroup $group,
        GenerateGroupInvoiceAction $generateInvoice,
    ): RedirectResponse {
        $data = $request->validated();
        $mode = isset($data['mode']) ? GroupInvoiceMode::from($data['mode']) : null;

        try {
            $result = $generateInvoice(
                $group,
                $mode,
                $data['folio_ids'] ?? null,
                $request->user(),
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $invoiceNos = collect($result['invoices'])->pluck('invoice_no')->implode(', ');

        return back()->with('success', "Invoice(s) generated: {$invoiceNos}");
    }
}
