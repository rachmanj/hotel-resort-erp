<?php

namespace App\Http\Controllers;

use App\Enums\GuestIdType;
use App\Enums\GuestIncidentType;
use App\Enums\VipTier;
use App\Http\Requests\StoreGuestRequest;
use App\Http\Requests\UpdateGuestRequest;
use App\Models\Guest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuestController extends Controller
{
    public function index(Request $request): Response
    {
        $guests = Guest::query()
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request): void {
                $search = $request->string('search')->toString();
                $q->where(function ($query) use ($search): void {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('id_number', 'like', "%{$search}%");
                });
            })
            ->orderBy('full_name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Guest $guest) => [
                'id' => $guest->id,
                'full_name' => $guest->full_name,
                'phone' => $guest->phone,
                'email' => $guest->email,
                'id_number' => $guest->id_number,
                'vip_tier' => $guest->vip_tier->value,
                'vip_tier_label' => $guest->vip_tier->label(),
                'is_blacklisted' => $guest->is_blacklisted,
            ]);

        return Inertia::render('Guests/Index', [
            'guests' => $guests,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Guests/Create', $this->formOptions());
    }

    public function store(StoreGuestRequest $request): RedirectResponse
    {
        $guest = Guest::query()->create($request->validated());

        return redirect()
            ->route('guests.show', $guest)
            ->with('success', 'Guest created successfully.');
    }

    public function show(Guest $guest): Response
    {
        $guest->load(['preferences', 'stays.room', 'stays.reservation', 'incidents.reportedBy:id,name']);

        return Inertia::render('Guests/Show', [
            'guest' => [
                'id' => $guest->id,
                'full_name' => $guest->full_name,
                'id_number' => $guest->id_number,
                'id_type' => $guest->id_type?->value,
                'id_type_label' => $guest->id_type?->label(),
                'phone' => $guest->phone,
                'email' => $guest->email,
                'address' => $guest->address,
                'nationality' => $guest->nationality,
                'vip_tier' => $guest->vip_tier->value,
                'vip_tier_label' => $guest->vip_tier->label(),
                'is_blacklisted' => $guest->is_blacklisted,
                'blacklist_reason' => $guest->blacklist_reason,
                'preferences' => $guest->preferences->map(fn ($p) => [
                    'id' => $p->id,
                    'key' => $p->key,
                    'value' => $p->value,
                    'notes' => $p->notes,
                ]),
                'stays' => $guest->stays->map(fn ($s) => [
                    'id' => $s->id,
                    'room_number' => $s->room?->number,
                    'reservation_code' => $s->reservation?->reservation_code,
                    'check_in_at' => $s->check_in_at?->toDateTimeString(),
                    'check_out_at' => $s->check_out_at?->toDateTimeString(),
                    'nights' => $s->nights,
                    'total_spend' => $s->total_spend,
                ]),
                'incidents' => $guest->incidents->map(fn ($i) => [
                    'id' => $i->id,
                    'type' => $i->type->value,
                    'type_label' => $i->type->label(),
                    'description' => $i->description,
                    'occurred_at' => $i->occurred_at?->toDateTimeString(),
                    'reported_by' => $i->reportedBy?->only(['id', 'name']),
                ]),
            ],
            'canEdit' => request()->user()?->can('guests.edit') ?? false,
        ]);
    }

    public function edit(Guest $guest): Response
    {
        return Inertia::render('Guests/Edit', [
            'guest' => $guest->only([
                'id', 'full_name', 'id_number', 'id_type', 'phone', 'email',
                'address', 'nationality', 'vip_tier', 'is_blacklisted', 'blacklist_reason',
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateGuestRequest $request, Guest $guest): RedirectResponse
    {
        $guest->update($request->validated());

        return redirect()
            ->route('guests.show', $guest)
            ->with('success', 'Guest updated successfully.');
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request->string('q')->trim()->toString();

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $guests = Guest::query()
            ->where(function ($query) use ($search): void {
                $query->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('id_number', 'like', "%{$search}%");
            })
            ->orderBy('full_name')
            ->limit(20)
            ->get(['id', 'full_name', 'phone', 'id_number', 'email']);

        return response()->json($guests);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'idTypes' => collect(GuestIdType::cases())->map(fn (GuestIdType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'vipTiers' => collect(VipTier::cases())->map(fn (VipTier $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'incidentTypes' => collect(GuestIncidentType::cases())->map(fn (GuestIncidentType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ];
    }
}
