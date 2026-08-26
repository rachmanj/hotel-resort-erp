<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BoatCharterStatus;
use App\Enums\BoatCharterType;
use App\Enums\FolioItemType;
use App\Enums\FolioStatus;
use App\Enums\GuideType;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBoatCharterRequest;
use App\Http\Requests\Admin\UpdateBoatCharterRequest;
use App\Models\BoatCharter;
use App\Models\BoatUnit;
use App\Models\DivePackage;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\RevenueCategory;
use App\Services\FolioPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BoatCharterController extends Controller
{
    public function __construct(
        private FolioPostingService $folioPostingService,
    ) {}

    public function index(Request $request): Response
    {
        $boatCharters = BoatCharter::query()
            ->with([
                'boatUnit:id,name',
                'divePackage:id,name',
                'reservation.guest:id,full_name',
                'folio:id,folio_no',
            ])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($q) use ($search): void {
                    $q->where('destination', 'like', "%{$search}%")
                        ->orWhereHas('reservation.guest', fn ($guestQuery) => $guestQuery->where('full_name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('trip_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (BoatCharter $charter) => [
                'id' => $charter->id,
                'trip_date' => $charter->trip_date->toDateString(),
                'destination' => $charter->destination,
                'charter_type' => $charter->charter_type->value,
                'charter_type_label' => $charter->charter_type->label(),
                'price' => (float) $charter->price,
                'quantity' => $charter->quantity,
                'guide_type' => $charter->guide_type->value,
                'guide_type_label' => $charter->guide_type->label(),
                'guide_fee' => $charter->guide_fee !== null ? (float) $charter->guide_fee : null,
                'bbm_liters' => $charter->bbm_liters !== null ? (float) $charter->bbm_liters : null,
                'bbm_cost' => $charter->bbm_cost !== null ? (float) $charter->bbm_cost : null,
                'status' => $charter->status->value,
                'status_label' => $charter->status->label(),
                'boat_unit_id' => $charter->boat_unit_id,
                'boat_name' => $charter->boatUnit?->name,
                'dive_package_id' => $charter->dive_package_id,
                'dive_package_name' => $charter->divePackage?->name,
                'reservation_id' => $charter->reservation_id,
                'guest_name' => $charter->reservation?->guest?->full_name,
                'folio_id' => $charter->folio_id,
                'folio_no' => $charter->folio?->folio_no,
                'notes' => $charter->notes,
            ]);

        return Inertia::render('Admin/BoatCharters/Index', [
            'boatCharters' => $boatCharters,
            'filters' => $request->only(['search']),
            'boatUnits' => BoatUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'divePackages' => DivePackage::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'reservations' => Reservation::query()
                ->with('guest:id,full_name')
                ->whereIn('status', [
                    ReservationStatus::Confirmed->value,
                    ReservationStatus::CheckedIn->value,
                ])
                ->orderByDesc('arrival_date')
                ->limit(100)
                ->get()
                ->map(fn (Reservation $reservation) => [
                    'id' => $reservation->id,
                    'reservation_code' => $reservation->reservation_code,
                    'guest_name' => $reservation->guest?->full_name,
                ]),
            'folios' => Folio::query()
                ->with('guest:id,full_name')
                ->where('status', FolioStatus::Open->value)
                ->orderByDesc('opened_at')
                ->limit(100)
                ->get()
                ->map(fn (Folio $folio) => [
                    'id' => $folio->id,
                    'folio_no' => $folio->folio_no,
                    'guest_name' => $folio->guest?->full_name,
                ]),
            'charterTypes' => collect(BoatCharterType::cases())->map(fn (BoatCharterType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'guideTypes' => collect(GuideType::cases())->map(fn (GuideType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'statusOptions' => collect(BoatCharterStatus::cases())->map(fn (BoatCharterStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    public function store(StoreBoatCharterRequest $request): RedirectResponse
    {
        BoatCharter::query()->create($request->validated());

        return back()->with('success', 'Boat charter created successfully.');
    }

    public function update(UpdateBoatCharterRequest $request, BoatCharter $boatCharter): RedirectResponse
    {
        $boatCharter->update($request->validated());

        return back()->with('success', 'Boat charter updated successfully.');
    }

    public function destroy(BoatCharter $boatCharter): RedirectResponse
    {
        if ($boatCharter->folio_item_id !== null) {
            return back()->with('error', 'Cannot delete boat charter that has been billed to a folio.');
        }

        $boatCharter->delete();

        return back()->with('success', 'Boat charter deleted successfully.');
    }

    public function bill(Request $request, BoatCharter $boatCharter): RedirectResponse
    {
        if ($boatCharter->status === BoatCharterStatus::Billed) {
            return back()->with('error', 'Boat charter is already billed.');
        }

        $folio = $boatCharter->folio_id !== null
            ? Folio::query()->find($boatCharter->folio_id)
            : null;

        if ($folio === null) {
            $reservation = $boatCharter->reservation_id !== null
                ? Reservation::query()->with('guest')->find($boatCharter->reservation_id)
                : null;

            if ($reservation === null) {
                return back()->with('error', 'Link a folio or reservation first.');
            }

            $folio = $this->folioPostingService->findOrCreateMasterFolio(
                $reservation->hotel_id,
                $reservation->id,
                $reservation->guest_id,
            );
        }

        $categoryCode = $boatCharter->charter_type === BoatCharterType::Diving ? 'dive_center' : 'boat';

        $revenueCategory = RevenueCategory::query()
            ->where('hotel_id', $boatCharter->hotel_id)
            ->where('code', $categoryCode)
            ->first();

        $description = sprintf(
            'Dive: %s · %d pax',
            $boatCharter->destination,
            $boatCharter->quantity,
        );

        try {
            DB::transaction(function () use ($boatCharter, $folio, $description, $revenueCategory, $request): void {
                $folioItem = $this->folioPostingService->postCharge(
                    folio: $folio,
                    itemType: FolioItemType::Misc->value,
                    description: $description,
                    amount: (float) $boatCharter->price,
                    quantity: (float) $boatCharter->quantity,
                    referenceType: 'boat_charter',
                    referenceId: $boatCharter->id,
                    postedBy: $request->user(),
                    applyTax: true,
                    revenueCategoryId: $revenueCategory?->id,
                );

                $boatCharter->update([
                    'folio_id' => $folio->id,
                    'folio_item_id' => $folioItem->id,
                    'status' => BoatCharterStatus::Billed->value,
                ]);
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Boat charter billed to folio successfully.');
    }
}
