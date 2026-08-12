<?php

namespace App\Actions\Groups;

use App\Enums\FolioItemType;
use App\Enums\FolioType;
use App\Models\Folio;
use App\Models\ReservationGroup;
use App\Models\User;
use App\Observers\ActivityLogObserver;
use App\Services\FolioPostingService;
use App\Services\GroupBookingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeductGroupDepositAction
{
    public function __construct(
        private FolioPostingService $folioPostingService,
        private GroupBookingService $groupBookingService,
    ) {}

    public function __invoke(ReservationGroup $group, ?User $performedBy = null): void
    {
        DB::transaction(function () use ($group, $performedBy): void {
            $depositBalance = $this->groupBookingService->getDepositBalance($group);

            if ($depositBalance <= 0) {
                return;
            }

            $memberFolios = $this->getMemberMasterFolios($group);

            if ($memberFolios->isEmpty()) {
                return;
            }

            $perFolioAmount = round($depositBalance / $memberFolios->count(), 2);
            $remaining = $depositBalance;

            foreach ($memberFolios->values() as $index => $folio) {
                $amount = $index === $memberFolios->count() - 1
                    ? $remaining
                    : $perFolioAmount;

                $remaining -= $amount;

                if ($amount <= 0) {
                    continue;
                }

                $this->folioPostingService->postCharge(
                    $folio,
                    FolioItemType::DepositCredit->value,
                    "Group deposit credit — {$group->group_code}",
                    -$amount,
                    1,
                    ReservationGroup::class,
                    $group->id,
                    $performedBy,
                    applyTax: false,
                );
            }

            $depositFolio = Folio::query()
                ->where('reservation_group_id', $group->id)
                ->where('type', FolioType::GroupDeposit->value)
                ->first();

            if ($depositFolio !== null) {
                $this->folioPostingService->closeFolio($depositFolio);
            }

            if ($performedBy !== null) {
                ActivityLogObserver::logCustom(
                    $group,
                    'deposit_deducted',
                    'Group deposit Rp'.number_format($depositBalance, 0, ',', '.')." allocated across member folios by {$performedBy->name}",
                    $performedBy->id,
                );
            }
        });
    }

    /**
     * @return Collection<int, Folio>
     */
    private function getMemberMasterFolios(ReservationGroup $group): Collection
    {
        $reservationIds = $group->reservations()->pluck('id');

        return Folio::query()
            ->whereIn('reservation_id', $reservationIds)
            ->where('type', FolioType::Master->value)
            ->get();
    }
}
