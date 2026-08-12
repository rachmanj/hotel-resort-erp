<?php

namespace App\Services;

use App\Enums\FolioType;
use App\Enums\GroupInvoiceMode;
use App\Enums\GroupStatus;
use App\Enums\ReservationRoomStatus;
use App\Enums\ReservationStatus;
use App\Models\Folio;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use Illuminate\Support\Collection;

class GroupBookingService
{
    public function __construct(
        private FolioPostingService $folioPostingService,
    ) {}

    /**
     * @return Collection<int, Reservation>
     */
    public function getMemberReservations(ReservationGroup $group): Collection
    {
        return $group->reservations()
            ->with(['guest', 'reservationRooms.room', 'reservationRooms.roomType'])
            ->orderBy('reservation_code')
            ->get();
    }

    public function getConsolidatedBalance(ReservationGroup $group): float
    {
        if ($group->invoice_mode === GroupInvoiceMode::Consolidated) {
            $consolidatedFolio = Folio::query()
                ->where('reservation_group_id', $group->id)
                ->where('type', FolioType::GroupConsolidated->value)
                ->first();

            if ($consolidatedFolio !== null) {
                return $this->folioPostingService->getBalance($consolidatedFolio);
            }
        }

        $total = 0.0;

        foreach ($this->getMemberReservations($group) as $reservation) {
            $folio = Folio::query()
                ->where('reservation_id', $reservation->id)
                ->where('type', FolioType::Master->value)
                ->first();

            if ($folio !== null) {
                $total += $this->folioPostingService->getBalance($folio);
            }
        }

        return round($total, 2);
    }

    public function syncGroupDates(ReservationGroup $group): void
    {
        $reservations = $group->reservations()->get(['arrival_date', 'departure_date']);

        if ($reservations->isEmpty()) {
            $group->update([
                'arrival_date' => null,
                'departure_date' => null,
            ]);

            return;
        }

        $group->update([
            'arrival_date' => $reservations->min('arrival_date'),
            'departure_date' => $reservations->max('departure_date'),
        ]);
    }

    public function refreshGroupStatus(ReservationGroup $group): void
    {
        $reservations = $group->reservations()->get();

        if ($reservations->isEmpty()) {
            if ($group->status !== GroupStatus::Cancelled) {
                $group->update(['status' => GroupStatus::Draft->value]);
            }

            return;
        }

        $statuses = $reservations->pluck('status');

        if ($statuses->every(fn (ReservationStatus $s) => $s === ReservationStatus::CheckedOut)) {
            $group->update(['status' => GroupStatus::CheckedOut->value]);

            return;
        }

        if ($statuses->contains(ReservationStatus::CheckedOut)) {
            $group->update(['status' => GroupStatus::PartiallyCheckedOut->value]);

            return;
        }

        if ($statuses->every(fn (ReservationStatus $s) => $s === ReservationStatus::CheckedIn)) {
            $group->update(['status' => GroupStatus::CheckedIn->value]);

            return;
        }

        if ($statuses->contains(ReservationStatus::CheckedIn)) {
            $group->update(['status' => GroupStatus::PartiallyCheckedIn->value]);

            return;
        }

        if ($statuses->every(fn (ReservationStatus $s) => $s === ReservationStatus::Confirmed)) {
            $group->update(['status' => GroupStatus::Confirmed->value]);

            return;
        }

        if ($statuses->contains(ReservationStatus::Cancelled) && $statuses->every(
            fn (ReservationStatus $s) => in_array($s, [ReservationStatus::Cancelled, ReservationStatus::Confirmed], true)
        )) {
            $group->update(['status' => GroupStatus::Confirmed->value]);
        }
    }

    public function countRooms(ReservationGroup $group): int
    {
        return (int) $group->reservations()
            ->withCount('reservationRooms')
            ->get()
            ->sum('reservation_rooms_count');
    }

    public function getDepositBalance(ReservationGroup $group): float
    {
        $depositFolio = Folio::query()
            ->where('reservation_group_id', $group->id)
            ->where('type', FolioType::GroupDeposit->value)
            ->first();

        if ($depositFolio === null) {
            return 0.0;
        }

        return -$this->folioPostingService->getBalance($depositFolio);
    }

    /**
     * @return Collection<int, ReservationRoom>
     */
    public function getCheckedInRooms(ReservationGroup $group): Collection
    {
        return $this->getMemberReservations($group)
            ->flatMap(fn (Reservation $r) => $r->reservationRooms)
            ->filter(fn ($rr) => $rr->status === ReservationRoomStatus::CheckedIn);
    }
}
